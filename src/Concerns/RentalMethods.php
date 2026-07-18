<?php

namespace VirtualSMS\Concerns;

use VirtualSMS\Exceptions\VirtualSMSException;
use VirtualSMS\Support\Arr;
use VirtualSMS\Support\PlatformCountryIds;

/**
 * Rentals (9 in-scope methods). Two tiers, both refund-identical (full
 * refund within 20 min of purchase, before first SMS): full_access (local
 * SIM inventory, any service) and platform (sourced via our global supplier
 * network, one service per number, 24/72/168h durations only). Never name
 * the supplier — "our global supplier network" / "platform tier" only.
 *
 * release_rental is explicitly out of scope for v2.0.0 (gated on the MCP
 * surface behind an undocumented fee policy) and is intentionally NOT
 * implemented here.
 */
trait RentalMethods
{
    /** Raw Full-Access pricing tiers (catalog dump, not authoritative for what's purchasable today). Public. */
    public function rentals_pricing(): array
    {
        $res = $this->request('GET', '/api/v1/rentals/pricing', [], null, false);
        return Arr::isList($res) ? $res : ($res['items'] ?? []);
    }

    /** List country availability + pricing per tier. Public. */
    public function rentals_available(array $options = []): array
    {
        $tier = $options['tier'] ?? 'full_access';
        $query = [
            'country' => $options['country'] ?? null,
            'service' => $options['service'] ?? null,
            'type' => $options['type'] ?? null,
            'provider' => $tier === 'platform' ? 'network' : null,
        ];
        return $this->request('GET', '/api/v1/rentals/available', $query, null, false);
    }

    /** List platform-tier services available in a country with stock + retail price. Public. Explicit field allowlist — never forward an internal supplier field. */
    public function rentals_services(string $countryCode, int $durationHours = 24): array
    {
        $res = $this->request('GET', '/api/v1/rentals/services', [
            'country_code' => $countryCode,
            'duration' => $durationHours,
        ], null, false);
        $raw = Arr::isList($res) ? $res : ($res['items'] ?? []);
        $out = [];
        foreach ((array) $raw as $s) {
            $out[] = [
                'service_id' => (string) ($s['service_id'] ?? ''),
                'service_name' => (string) ($s['service_name'] ?? ''),
                'physical_count' => (int) ($s['physical_count'] ?? 0),
                'our_price' => isset($s['our_price']) ? (float) $s['our_price'] : null,
                'base_price' => isset($s['base_price']) ? (float) $s['base_price'] : null,
                'popular' => (bool) ($s['popular'] ?? false),
                'icon_url' => isset($s['icon_url']) ? (string) $s['icon_url'] : null,
            ];
        }
        return $out;
    }

    /** Get catalog price for a (service, country, duration) platform-tier combo. Public. */
    public function rentals_price(string $service, string $countryCode, int $durationHours): array
    {
        return $this->request('GET', '/api/v1/rentals/price', [
            'service' => $service,
            'country_code' => $countryCode,
            'duration' => $durationHours,
        ], null, false);
    }

    /**
     * Create a rental (either tier).
     *
     * tier=full_access -> POST /api/v1/rentals {country, rental_type, duration_hours, service?, auto_renew?}
     * tier=platform     -> resolves country_code (ISO-2) to the internal numeric ID via
     *                       PlatformCountryIds, then POST /api/v1/rentals/provider
     *                       {service, country: numericID, duration_hours, provider: "network"}
     *
     * @param array{
     *   tier: string,
     *   country: string,
     *   duration_hours: int,
     *   service?: string,
     *   auto_renew?: bool
     * } $params
     */
    public function create_rental(array $params): array
    {
        $tier = $params['tier'] ?? 'full_access';

        if ($tier === 'platform') {
            $countryId = PlatformCountryIds::resolve($params['country']);
            if ($countryId === null) {
                throw new VirtualSMSException(
                    "Platform-tier rentals are not available for country_code \"{$params['country']}\". " .
                    'Use rentals_available with tier=platform to see supported countries.'
                );
            }
            $res = $this->request('POST', '/api/v1/rentals/provider', [], [
                'service' => $params['service'] ?? null,
                'country' => $countryId,
                'duration_hours' => $params['duration_hours'],
                'provider' => 'network',
            ]);
            return [
                'success' => (bool) ($res['success'] ?? true),
                'rental_id' => (string) ($res['rental_id'] ?? ''),
                'phone_number' => (string) ($res['phone_number'] ?? ''),
                'expires_at' => (string) ($res['expires_at'] ?? ''),
                'retail_cost' => isset($res['retail_cost']) ? (float) $res['retail_cost'] : null,
                'currency' => isset($res['currency']) ? (string) $res['currency'] : null,
                'status' => 'active',
            ];
        }

        return $this->request('POST', '/api/v1/rentals', [], [
            'country' => $params['country'],
            'rental_type' => $params['rental_type'] ?? 'full',
            'duration_hours' => $params['duration_hours'],
            'service' => $params['service'] ?? null,
            'auto_renew' => $params['auto_renew'] ?? false,
        ]);
    }

    /** List rentals, optional status filter. */
    public function list_rentals(?string $status = null): array
    {
        $res = $this->request('GET', '/api/v1/rentals', $status !== null ? ['status' => $status] : []);
        return Arr::isList($res) ? $res : ($res['items'] ?? []);
    }

    /**
     * Get one rental by id. No dedicated GET-by-id backend route exists (by
     * design) — this fetches list_rentals('all') and finds by id, exactly
     * like the MCP client.
     */
    public function get_rental(string $rentalId): ?array
    {
        foreach ($this->list_rentals('all') as $r) {
            if (($r['id'] ?? null) === $rentalId) {
                return $r;
            }
        }
        return null;
    }

    /** Extend an active rental, charged at current catalog price. */
    public function extend_rental(string $rentalId, int $durationHours): array
    {
        return $this->request('POST', '/api/v1/rentals/' . rawurlencode($rentalId) . '/extend', [], [
            'duration_hours' => $durationHours,
        ]);
    }

    /** Full refund: only within 20 min of purchase and before first SMS, either tier. */
    public function cancel_rental(string $rentalId): array
    {
        return $this->request('POST', '/api/v1/rentals/' . rawurlencode($rentalId) . '/cancel', [], []);
    }
}
