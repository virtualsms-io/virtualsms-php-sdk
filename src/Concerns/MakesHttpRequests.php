<?php

namespace VirtualSMS\Concerns;

use VirtualSMS\Exceptions\ApiException;
use VirtualSMS\Exceptions\BadApiKeyException;
use VirtualSMS\Exceptions\InsufficientBalanceException;
use VirtualSMS\Exceptions\NotFoundException;
use VirtualSMS\Exceptions\RateLimitedException;
use VirtualSMS\Exceptions\ServerErrorException;
use VirtualSMS\Exceptions\VirtualSMSException;

/**
 * Low-level HTTP transport shared by every method group. Implements the
 * same GET-only bounded retry, idempotency-key generation, and status-code
 * error mapping as the reference MCP client (src/client.ts).
 */
trait MakesHttpRequests
{
    /** Max total attempts for a GET/HEAD request: 1 initial + 2 retries. */
    private const GET_RETRY_MAX_ATTEMPTS = 3;
    private const GET_RETRY_BASE_DELAY_MS = 300;

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, array $query = [], ?array $body = null, bool $auth = true): array
    {
        $method = strtoupper($method);
        $isMutating = !in_array($method, ['GET', 'HEAD'], true);

        $url = rtrim($this->baseUrl, '/') . $path;
        if (!empty($query)) {
            $filtered = array_filter($query, static fn ($v) => $v !== null);
            if (!empty($filtered)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($filtered);
            }
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($auth) {
            $this->requireApiKey();
            $headers[] = 'X-API-Key: ' . $this->apiKey;
        }
        if ($isMutating) {
            $headers[] = 'X-Idempotency-Key: ' . self::uuidv4();
        }

        $payload = $body !== null ? json_encode($body) : null;

        $attempt = 0;
        do {
            $attempt++;
            [$status, $responseBody, $curlError] = $this->executeCurl($method, $url, $headers, $payload);

            $hasResponse = $curlError === null;

            if ($hasResponse && $status < 400) {
                $decoded = json_decode((string) $responseBody, true);
                return is_array($decoded) ? $decoded : [];
            }

            $shouldRetry = !$isMutating
                && $attempt < self::GET_RETRY_MAX_ATTEMPTS
                && (!$hasResponse || $status >= 500);

            if ($shouldRetry) {
                usleep(self::GET_RETRY_BASE_DELAY_MS * (2 ** ($attempt - 1)) * 1000);
                continue;
            }

            $this->throwForFailure($status, $responseBody, $curlError, $isMutating);
        } while ($attempt < self::GET_RETRY_MAX_ATTEMPTS);

        // Unreachable in practice: throwForFailure always throws. Kept for static analysis.
        throw new VirtualSMSException('Request failed after retries.');
    }

    /**
     * @param string[] $headers
     * @return array{0:int,1:?string,2:?string} [statusCode, body, curlErrorMessageOrNull]
     */
    private function executeCurl(string $method, string $url, array $headers, ?string $payload): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch) ?: 'unknown transport error';
            curl_close($ch);
            return [0, null, $error];
        }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$status, (string) $body, null];
    }

    /**
     * @throws VirtualSMSException
     */
    private function throwForFailure(int $status, ?string $rawBody, ?string $curlError, bool $isMutating): void
    {
        if ($curlError !== null) {
            throw new ServerErrorException("Network error contacting VirtualSMS: {$curlError}", !$isMutating);
        }

        $decoded = json_decode((string) $rawBody, true);
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? $decoded['error'] ?? $rawBody) : (string) $rawBody;
        if ($message === '') {
            $message = "HTTP {$status}";
        }

        if ($status === 401) {
            throw new BadApiKeyException('Invalid API key. Get one at https://virtualsms.io');
        }
        if ($status === 402) {
            throw new InsufficientBalanceException('Insufficient balance. Top up at https://virtualsms.io');
        }
        if ($status === 404) {
            throw new NotFoundException("Not found: {$message}");
        }
        if ($status === 429) {
            throw new RateLimitedException('Rate limit exceeded. Please slow down requests.');
        }
        if ($status >= 500) {
            if ($isMutating) {
                throw new ServerErrorException(
                    "VirtualSMS had a server error ({$status}) on a request that may have made a purchase or changed " .
                    "state. DO NOT blindly retry: first verify with a list/get call (e.g. list_orders, list_rentals, " .
                    "get_order) whether it actually succeeded, as you may have been charged. Details: {$message}",
                    false
                );
            }
            throw new ServerErrorException("VirtualSMS server error ({$status}). Safe to retry this read-only request. Details: {$message}", true);
        }

        throw new ApiException("API error: {$message}", $status);
    }

    private static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
