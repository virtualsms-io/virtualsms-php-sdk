<?php

namespace VirtualSMS;

use VirtualSMS\Concerns\AccountMethods;
use VirtualSMS\Concerns\MakesHttpRequests;
use VirtualSMS\Concerns\OrderMethods;
use VirtualSMS\Concerns\ProxyMethods;
use VirtualSMS\Concerns\RentalMethods;
use VirtualSMS\Concerns\SessionMethods;
use VirtualSMS\Concerns\ToolMethods;
use VirtualSMS\Concerns\WebhookMethods;
use VirtualSMS\Exceptions\BadApiKeyException;

/**
 * VirtualSMS PHP SDK v2.0.0 — a native client for the VirtualSMS REST API v1.
 *
 * Real carrier mobile numbers (not VoIP), matching-country proxies, and
 * short-term number rentals, all reachable through one typed client.
 *
 * @see https://virtualsms.io
 * @see https://virtualsms.io/docs
 */
class VirtualSMS
{
    use MakesHttpRequests;
    use OrderMethods;
    use RentalMethods;
    use ProxyMethods;
    use AccountMethods;
    use SessionMethods;
    use ToolMethods;
    use WebhookMethods;

    private ?string $apiKey;
    private string $baseUrl;
    private int $timeout;

    /**
     * @param string|null $apiKey Your API key from https://virtualsms.io (Settings -> API Keys).
     *                            Required for authenticated calls; public endpoints (list_services,
     *                            list_countries, get_price, check_number, ...) work without one.
     * @param array{baseUrl?: string, timeout?: int} $options
     *                            baseUrl defaults to https://virtualsms.io (or $VIRTUALSMS_BASE_URL
     *                            if set). timeout is in seconds, default 30.
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->apiKey = $apiKey;
        $envBaseUrl = getenv('VIRTUALSMS_BASE_URL');
        $this->baseUrl = rtrim($options['baseUrl'] ?? ($envBaseUrl !== false ? $envBaseUrl : 'https://virtualsms.io'), '/');
        $this->timeout = $options['timeout'] ?? 30;
    }

    /** @throws BadApiKeyException if no API key was supplied to the constructor. */
    public function requireApiKey(): void
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new BadApiKeyException(
                'An API key is required for this operation. Get your API key at https://virtualsms.io'
            );
        }
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
}
