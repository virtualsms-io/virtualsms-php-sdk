<?php

namespace VirtualSMS\Support;

/**
 * Pure, no-network composition of ready-to-use proxy connection strings.
 *
 * Mirrors the frontend's ProxyEndpointGenerator (buildUsername()/buildEndpoint())
 * and the MCP server client's buildProxyUsername()/buildProxyEndpointString()
 * byte-identical: this is a shared client-side contract, not a backend call,
 * so drift here silently breaks connection strings a customer copies.
 */
final class ProxyEndpointBuilder
{
    private const HTTP_PORT = 823;
    private const SOCKS5_PORT = 824;

    /**
     * @param array{proxy_id:string,pool_type:string,proxy_host:string,proxy_port:int,proxy_login:string,proxy_password:string} $proxy
     * @param array{
     *   country_code:string,
     *   target_by?:string,
     *   location_code?:?string,
     *   session?:string,
     *   sticky_ttl_minutes?:int,
     *   count?:int,
     *   protocol?:string,
     *   format?:string
     * } $params
     * @return array<string,mixed>
     */
    public static function build(array $proxy, array $params): array
    {
        $targetBy = $params['target_by'] ?? 'country';
        $session = $params['session'] ?? 'rotating';
        $protocol = $params['protocol'] ?? 'HTTP';
        $format = $params['format'] ?? 'host:port:user:pass';
        $ttl = $params['sticky_ttl_minutes'] ?? 10;
        $count = max(1, min(100, (int) ($params['count'] ?? 1)));
        $port = $protocol === 'SOCKS5' ? self::SOCKS5_PORT : self::HTTP_PORT;
        $locationCode = $params['location_code'] ?? null;

        $premium2x = $targetBy !== 'country'
            && trim((string) $locationCode) !== ''
            && ($proxy['pool_type'] ?? '') !== 'residential_premium';

        $endpoints = [];
        if ($session === 'rotating') {
            $user = self::buildUsername($proxy['proxy_login'], $params['country_code'], $targetBy, $locationCode);
            $ep = self::buildEndpointString($proxy['proxy_host'], $port, $user, $proxy['proxy_password'], $format, $protocol);
            for ($i = 0; $i < $count; $i++) {
                $endpoints[] = $ep;
            }
        } else {
            for ($i = 0; $i < $count; $i++) {
                $user = self::buildUsername($proxy['proxy_login'], $params['country_code'], $targetBy, $locationCode, $i + 1, $ttl);
                $endpoints[] = self::buildEndpointString($proxy['proxy_host'], $port, $user, $proxy['proxy_password'], $format, $protocol);
            }
        }

        return [
            'proxy_id' => $proxy['proxy_id'],
            'pool_type' => $proxy['pool_type'],
            'host' => $proxy['proxy_host'],
            'port' => $port,
            'protocol' => $protocol,
            'session' => $session,
            'sticky_ttl_minutes' => $session === 'sticky' ? $ttl : null,
            'country_code' => $params['country_code'],
            'target_by' => $targetBy,
            'location_code' => $locationCode,
            'premium_2x' => $premium2x,
            'endpoints' => $endpoints,
        ];
    }

    private static function buildUsername(
        string $login,
        string $countryCode,
        string $targetBy,
        ?string $locationCode,
        ?int $stickyIndex = null,
        ?int $stickyMinutes = null
    ): string {
        $u = $login . '__cr.' . strtolower($countryCode);
        $loc = trim((string) $locationCode);
        if ($loc !== '' && $targetBy !== 'country') {
            if ($targetBy === 'state') {
                $u .= ';state.' . strtolower($loc);
            } elseif ($targetBy === 'city') {
                $u .= ';city.' . strtolower($loc);
            } elseif ($targetBy === 'zip') {
                $u .= ';zip.' . $loc;
            } elseif ($targetBy === 'asn') {
                $u .= ';asn.' . $loc;
            }
        }
        if ($stickyIndex !== null) {
            $u .= ';sessid.s' . $stickyIndex . ';sessttl.' . ($stickyMinutes ?? 10);
        }
        return $u;
    }

    private static function buildEndpointString(
        string $host,
        int $port,
        string $user,
        string $pass,
        string $format,
        string $protocol
    ): string {
        if ($format === 'host:port:user:pass') {
            return "{$host}:{$port}:{$user}:{$pass}";
        }
        if ($format === 'user:pass@host:port') {
            return "{$user}:{$pass}@{$host}:{$port}";
        }
        $scheme = $protocol === 'SOCKS5' ? 'socks5h' : 'http';
        return "curl -x \"{$scheme}://{$user}:{$pass}@{$host}:{$port}\" https://api.ipify.org";
    }
}
