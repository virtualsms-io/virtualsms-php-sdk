<?php

namespace VirtualSMS\Concerns;

use VirtualSMS\Exceptions\NotFoundException;
use VirtualSMS\Support\Arr;
use VirtualSMS\Support\ProxyEndpointBuilder;

/**
 * Proxies (10 methods): catalog, owned proxies, purchase, rotate, usage,
 * targeting, test-dial, location discovery, and the pure client-side
 * connection-string composer (generate_proxy_endpoint).
 */
trait ProxyMethods
{
    /** List pool types, countries, price/GB. Public, ~10min cache upstream. */
    public function list_proxy_catalog(): array
    {
        $res = $this->request('GET', '/api/v1/proxies/catalog', [], null, false);
        $raw = $res['pool_types'] ?? (Arr::isList($res) ? $res : []);
        $out = [];
        foreach ((array) $raw as $p) {
            $countries = [];
            foreach ((array) ($p['countries'] ?? []) as $c) {
                $countries[] = [
                    'code' => (string) ($c['code'] ?? ''),
                    'name' => (string) ($c['name'] ?? ''),
                    'available' => (bool) ($c['available'] ?? false),
                    'ip_count' => (int) ($c['ip_count'] ?? 0),
                ];
            }
            $out[] = [
                'id' => (string) ($p['id'] ?? ''),
                'label' => (string) ($p['label'] ?? ''),
                'price_per_gb' => (float) ($p['price_per_gb'] ?? 0),
                'countries' => $countries,
            ];
        }
        return $out;
    }

    /** List owned proxies with credentials. */
    public function list_proxies(): array
    {
        $res = $this->request('GET', '/api/v1/proxies');
        $raw = Arr::isList($res) ? $res : [];
        $out = [];
        foreach ((array) $raw as $p) {
            $out[] = [
                'proxy_id' => (string) ($p['proxy_id'] ?? ''),
                'pool_type' => (string) ($p['pool_type'] ?? ''),
                'country_code' => (string) ($p['country_code'] ?? ''),
                'country_name' => isset($p['country_name']) ? (string) $p['country_name'] : null,
                'gb_total' => (float) ($p['gb_total'] ?? 0),
                'gb_used' => (float) ($p['gb_used'] ?? 0),
                'gb_remaining' => (float) ($p['gb_remaining'] ?? 0),
                'proxy_host' => (string) ($p['proxy_host'] ?? ''),
                'proxy_port' => (int) ($p['proxy_port'] ?? 0),
                'proxy_login' => (string) ($p['proxy_login'] ?? ''),
                'proxy_password' => (string) ($p['proxy_password'] ?? ''),
                'updated_at' => isset($p['updated_at']) ? (string) $p['updated_at'] : null,
                'created_at' => isset($p['created_at']) ? (string) $p['created_at'] : null,
            ];
        }
        return $out;
    }

    /**
     * Purchase proxy traffic (GB) for a pool type.
     *
     * @param array{pool_type:string, gb:float, country_code?:string, idempotency_key?:string} $params
     */
    public function buy_proxy(array $params): array
    {
        return $this->request('POST', '/api/v1/proxies', [], $params);
    }

    /** Get a fresh exit IP for an existing proxy. */
    public function rotate_proxy(string $proxyId, ?int $port = null): array
    {
        $body = $port !== null ? ['port' => $port] : [];
        return $this->request('POST', '/api/v1/proxies/' . rawurlencode($proxyId) . '/rotate', [], $body);
    }

    /** Cached GB used/remaining (refreshed ~5min, no upstream call). */
    public function get_proxy_usage(string $proxyId): array
    {
        $d = $this->request('GET', '/api/v1/proxies/' . rawurlencode($proxyId) . '/usage');
        return [
            'gb_used' => (float) ($d['gb_used'] ?? 0),
            'gb_remaining' => (float) ($d['gb_remaining'] ?? 0),
            'requests' => (int) ($d['requests'] ?? 0),
            'updated_at' => isset($d['updated_at']) ? (string) $d['updated_at'] : null,
        ];
    }

    /** Per-day GB/requests series, 7d or 30d. */
    public function get_proxy_usage_history(string $proxyId, string $range = '7d'): array
    {
        $d = $this->request('GET', '/api/v1/proxies/' . rawurlencode($proxyId) . '/usage-history', ['range' => $range]);
        $series = [];
        foreach ((array) ($d['series'] ?? []) as $p) {
            $series[] = [
                'date' => (string) ($p['date'] ?? ''),
                'gb' => (float) ($p['gb'] ?? 0),
                'requests' => (int) ($p['requests'] ?? 0),
            ];
        }
        $totals = (array) ($d['totals'] ?? []);
        return [
            'series' => $series,
            'totals' => [
                'gb' => (float) ($totals['gb'] ?? 0),
                'requests' => (int) ($totals['requests'] ?? 0),
            ],
        ];
    }

    /**
     * Persist default geo-targeting on a proxy sub-user. Country-only is
     * free; cities/asns bill the customer's own funded GB at 2x on
     * non-premium pools (free on residential_premium — backend returns
     * premium_2x so the caller can warn).
     */
    public function set_proxy_targeting(string $proxyId, string $countryCode, array $cities = [], array $asns = []): array
    {
        $d = $this->request('POST', '/api/v1/proxies/' . rawurlencode($proxyId) . '/targeting', [], [
            'country_code' => $countryCode,
            'cities' => $cities ?: null,
            'asns' => $asns ?: null,
        ]);
        return [
            'ok' => (bool) ($d['ok'] ?? false),
            'country_code' => (string) ($d['country_code'] ?? $countryCode),
            'premium_2x' => (bool) ($d['premium_2x'] ?? false),
        ];
    }

    /**
     * Dial out through the proxy, report exit IP/country/city/ISP/latency.
     * Rate-limited ~1/20s per proxy server-side (surfaces as RateLimitedException).
     */
    public function test_proxy(string $proxyId, string $country, ?string $session = null, ?string $protocol = null): array
    {
        $d = $this->request('POST', '/api/v1/proxies/' . rawurlencode($proxyId) . '/test', [], [
            'country' => $country,
            'session' => $session,
            'protocol' => $protocol,
        ]);
        return [
            'ok' => (bool) ($d['ok'] ?? false),
            'exit_ip' => isset($d['exit_ip']) ? (string) $d['exit_ip'] : null,
            'country_code' => isset($d['country_code']) ? (string) $d['country_code'] : null,
            'country_name' => isset($d['country_name']) ? (string) $d['country_name'] : null,
            'city' => isset($d['city']) ? (string) $d['city'] : null,
            'region' => isset($d['region']) ? (string) $d['region'] : null,
            'isp' => isset($d['isp']) ? (string) $d['isp'] : null,
            'asn' => isset($d['asn']) ? (string) $d['asn'] : null,
            'latency_ms' => isset($d['latency_ms']) ? (float) $d['latency_ms'] : null,
            'error' => isset($d['error']) ? (string) $d['error'] : null,
        ];
    }

    /**
     * Discover valid cities/states/asns/zips for a pool_type+country. Public,
     * no auth, 6h cache. NOT available for residential_premium.
     */
    public function list_proxy_locations(string $poolType, string $country, string $kind): array
    {
        $res = $this->request('GET', '/api/v1/proxies/locations', [
            'pool_type' => $poolType,
            'country' => $country,
            'kind' => $kind,
        ], null, false);
        $items = (array) ($res['items'] ?? []);
        $out = [];
        foreach ($items as $it) {
            $out[] = [
                'code' => (string) ($it['code'] ?? ''),
                'name' => (string) ($it['name'] ?? ''),
                'count' => (int) ($it['count'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Compose a ready-to-use connection string. No backend call, no
     * purchase: looks up credentials for proxy_id via list_proxies(), then
     * builds a username string encoding targeting (per
     * Support\ProxyEndpointBuilder, ported byte-identical from the frontend's
     * ProxyEndpointGenerator).
     *
     * @param array{
     *   proxy_id: string,
     *   country_code: string,
     *   target_by?: string,
     *   location_code?: string,
     *   session?: string,
     *   sticky_ttl_minutes?: int,
     *   count?: int,
     *   protocol?: string,
     *   format?: string
     * } $params
     */
    public function generate_proxy_endpoint(array $params): array
    {
        $this->requireApiKey();
        $proxies = $this->list_proxies();
        $proxy = null;
        foreach ($proxies as $p) {
            if ($p['proxy_id'] === $params['proxy_id']) {
                $proxy = $p;
                break;
            }
        }
        if ($proxy === null) {
            throw new NotFoundException("Not found: proxy {$params['proxy_id']} does not exist on this account");
        }
        return ProxyEndpointBuilder::build($proxy, $params);
    }
}
