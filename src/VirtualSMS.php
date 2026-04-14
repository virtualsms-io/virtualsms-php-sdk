<?php

namespace VirtualSMS;

/**
 * VirtualSMS PHP SDK — SMS verification with real physical SIM cards.
 *
 * @see https://virtualsms.io
 * @see https://virtualsms.io/api
 */
class VirtualSMS
{
    private string $apiKey;
    private string $baseUrl;

    /**
     * Create a VirtualSMS client.
     *
     * @param string $apiKey Your API key from https://virtualsms.io (Settings → API Keys)
     * @param string $baseUrl API base URL (default: production)
     */
    public function __construct(string $apiKey, string $baseUrl = 'https://virtualsms.io/stubs/handler_api.php')
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get current account balance in USD.
     *
     * @return float Account balance
     * @throws VirtualSMSException If API key is invalid
     *
     * @example
     * $client = new VirtualSMS('vsms_your_key');
     * echo "Balance: $" . $client->getBalance();
     */
    public function getBalance(): float
    {
        $result = $this->request('getBalance');
        if (str_starts_with($result, 'ACCESS_BALANCE:')) {
            return (float) explode(':', $result)[1];
        }
        throw new VirtualSMSException($result);
    }

    /**
     * Request a phone number for SMS verification.
     *
     * @param string $service Service code ('wa' for WhatsApp, 'tg' for Telegram)
     * @param int $country Country ID (187=US, 22=UK, 12=Germany)
     * @return Activation Phone number and activation details
     *
     * @example
     * $activation = $client->getNumber('wa', 22); // WhatsApp, UK
     * echo "Use number: " . $activation->phone;
     */
    public function getNumber(string $service, int $country = 187): Activation
    {
        $result = $this->request('getNumber', [
            'service' => $service,
            'country' => $country,
        ]);

        if (str_starts_with($result, 'ACCESS_NUMBER:')) {
            $parts = explode(':', $result);
            return new Activation(
                activationId: (int) $parts[1],
                phone: $parts[2],
                service: $service,
                country: $country
            );
        }

        if ($result === 'NO_NUMBERS') {
            throw new NoNumbersException("No numbers available for {$service} in country {$country}");
        }

        throw new VirtualSMSException($result);
    }

    /**
     * Check the status of an activation.
     *
     * @param int $activationId The activation ID from getNumber()
     * @return array{status: string, code: ?string}
     */
    public function getStatus(int $activationId): array
    {
        $result = $this->request('getStatus', ['id' => $activationId]);

        if ($result === 'STATUS_WAIT_CODE') {
            return ['status' => 'waiting', 'code' => null];
        }
        if (str_starts_with($result, 'STATUS_OK:')) {
            return ['status' => 'received', 'code' => explode(':', $result)[1]];
        }
        if ($result === 'STATUS_CANCEL') {
            return ['status' => 'cancelled', 'code' => null];
        }

        return ['status' => $result, 'code' => null];
    }

    /**
     * Mark activation as done (code used successfully).
     */
    public function done(int $activationId): string
    {
        return $this->request('setStatus', ['id' => $activationId, 'status' => 6]);
    }

    /**
     * Cancel activation and get automatic refund.
     */
    public function cancel(int $activationId): string
    {
        return $this->request('setStatus', ['id' => $activationId, 'status' => 8]);
    }

    /**
     * Wait for an SMS code to arrive.
     *
     * @param int $activationId The activation ID
     * @param int $timeout Max wait in seconds (default: 300)
     * @param int $pollInterval Seconds between checks (default: 5)
     * @return string|null The verification code, or null on timeout
     *
     * @example
     * $activation = $client->getNumber('wa');
     * $code = $client->waitForCode($activation->activationId);
     * if ($code) echo "WhatsApp code: $code";
     */
    public function waitForCode(int $activationId, int $timeout = 300, int $pollInterval = 5): ?string
    {
        $start = time();
        while (time() - $start < $timeout) {
            $result = $this->getStatus($activationId);
            if ($result['code'] !== null) {
                return $result['code'];
            }
            if ($result['status'] === 'cancelled') {
                return null;
            }
            sleep($pollInterval);
        }
        return null;
    }

    private function request(string $action, array $params = []): string
    {
        $params['action'] = $action;
        $params['api_key'] = $this->apiKey;

        $url = $this->baseUrl . '?' . http_build_query($params);
        $response = file_get_contents($url);

        if ($response === false) {
            throw new VirtualSMSException("HTTP request failed for action: {$action}");
        }

        return trim($response);
    }
}

class Activation
{
    public function __construct(
        public readonly int $activationId,
        public readonly string $phone,
        public readonly string $service,
        public readonly int $country,
    ) {}
}

class VirtualSMSException extends \RuntimeException {}
class NoNumbersException extends VirtualSMSException {}
