<?php

namespace VirtualSMS\Concerns;

use VirtualSMS\Exceptions\NotFoundException;
use VirtualSMS\Exceptions\VirtualSMSException;

/**
 * Activations / Orders (15 methods): buy a virtual number for one-off SMS
 * verification, poll or wait for the code, cancel/swap/retry, and the
 * client-side convenience helpers (get_sms, order_history, cancel_all_orders,
 * search_services, find_cheapest) that have no dedicated backend route.
 */
trait OrderMethods
{
    /** List all SMS-verification services (Telegram, WhatsApp, etc.). Public, no auth. */
    public function list_services(): array
    {
        $res = $this->request('GET', '/api/v1/customer/services', [], null, false);
        $raw = $res['services'] ?? $res;
        $out = [];
        foreach ((array) $raw as $s) {
            $out[] = [
                'code' => (string) ($s['service_id'] ?? $s['code'] ?? ''),
                'name' => (string) ($s['service_name'] ?? $s['name'] ?? ''),
                'icon' => isset($s['icon']) ? (string) $s['icon'] : null,
            ];
        }
        return $out;
    }

    /** List all available countries. Public, no auth. */
    public function list_countries(): array
    {
        $res = $this->request('GET', '/api/v1/customer/countries', [], null, false);
        $raw = $res['countries'] ?? $res;
        $out = [];
        foreach ((array) $raw as $c) {
            $out[] = [
                'iso' => (string) ($c['country_id'] ?? $c['iso'] ?? ''),
                'name' => (string) ($c['country_name'] ?? $c['name'] ?? ''),
                'flag' => isset($c['flag']) ? (string) $c['flag'] : null,
            ];
        }
        return $out;
    }

    /**
     * Check price + REAL stock for a service+country combo. /api/v1/price
     * alone returns no availability field, so this fails closed: real stock
     * comes from a second call to the catalog endpoint's per-country `count`
     * field (count > 0 = in stock). Never report available=true off /price alone.
     */
    public function get_price(string $service, string $country): array
    {
        $priceRes = $this->request('GET', '/api/v1/price', ['service' => $service, 'country' => $country], null, false);
        $available = false;
        foreach ($this->get_catalog_countries($service) as $c) {
            if (strcasecmp($c['iso'], $country) === 0) {
                $available = $c['count'] > 0;
                break;
            }
        }
        return [
            'price_usd' => (float) ($priceRes['price'] ?? $priceRes['price_usd'] ?? 0),
            'currency' => (string) ($priceRes['currency'] ?? 'USD'),
            'available' => $available,
        ];
    }

    /**
     * Internal: catalog countries with real per-country stock. Backs get_price()
     * and find_cheapest(). Public endpoint, no auth.
     *
     * @return array<int,array{iso:string,name:string,price_usd:float,count:int}>
     */
    private function get_catalog_countries(string $service): array
    {
        $res = $this->request('GET', '/api/v1/catalog/countries', ['service' => $service], null, false);
        $raw = $res['countries'] ?? $res;
        $out = [];
        foreach ((array) $raw as $c) {
            $out[] = [
                'iso' => (string) ($c['id'] ?? $c['iso'] ?? $c['country'] ?? ''),
                'name' => (string) ($c['name'] ?? $c['country_name'] ?? ''),
                'price_usd' => (float) ($c['price'] ?? $c['our_price'] ?? $c['price_usd'] ?? 0),
                'count' => (int) ($c['count'] ?? 0),
            ];
        }
        return $out;
    }

    /** Buy a virtual number for one-off SMS verification. */
    public function create_order(string $service, string $country): array
    {
        return $this->normalizeOrder($this->request('POST', '/api/v1/customer/purchase', [], ['service' => $service, 'country' => $country]));
    }

    /** Full order detail incl. any received SMS. */
    public function get_order(string $orderId): array
    {
        return $this->normalizeOrder($this->request('GET', '/api/v1/customer/order/' . rawurlencode($orderId)));
    }

    /**
     * Normalizes messages[]/legacy sms_code/sms_text into one shape and
     * extracts the first 4-8 digit numeric run as `code`, mirroring the MCP
     * client's handleGetOrder/handleGetSms.
     */
    private function normalizeOrder(array $order): array
    {
        $messages = $order['messages'] ?? [];
        if (empty($messages) && (!empty($order['sms_text']) || !empty($order['sms_code']))) {
            $messages = [[
                'content' => $order['sms_text'] ?? $order['sms_code'] ?? '',
                'sender' => null,
                'received_at' => null,
            ]];
        }
        $firstContent = $messages[0]['content'] ?? null;
        $code = $order['sms_code'] ?? ($firstContent !== null ? $this->extractCode($firstContent) : null);

        if (!empty($messages)) {
            $order['messages'] = $messages;
        }
        if ($code !== null) {
            $order['code'] = $code;
            $order['sms_code'] = $code;
        }
        if ($firstContent !== null) {
            $order['sms_text'] = $firstContent;
        }
        return $order;
    }

    private function extractCode(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        return preg_match('/\b(\d{4,8})\b/', $text, $m) ? $m[1] : null;
    }

    /** Poll for SMS delivery on an order. Thin client-side wrapper over get_order(). */
    public function get_sms(string $orderId): array
    {
        $order = $this->get_order($orderId);
        $result = [
            'status' => $order['status'] ?? null,
            'phone_number' => $order['phone_number'] ?? null,
        ];
        if (!empty($order['messages'])) {
            $result['messages'] = $order['messages'];
        }
        if (!empty($order['code'])) {
            $result['code'] = $order['code'];
            $result['sms_code'] = $order['code'];
        }
        if (!empty($order['sms_text'])) {
            $result['sms_text'] = $order['sms_text'];
        }
        return $result;
    }

    /**
     * Block until an SMS arrives or timeout elapses. Polling-only baseline
     * for v2.0.0 (the MCP client also races an optional WebSocket; that is
     * documented here as a v2.1+ enhancement, not required for a synchronous
     * PHP client). Default timeout 300s / poll interval 5s, per spec §1.
     * Never throws on timeout — returns a structured {success:false} result.
     */
    public function wait_for_sms(string $orderId, int $timeoutSeconds = 300, int $intervalSeconds = 5): array
    {
        $start = time();
        $initial = $this->get_order($orderId);

        if (!empty($initial['messages']) || !empty($initial['sms_code']) || !empty($initial['sms_text'])) {
            return $this->buildWaitSuccess($orderId, $initial, 'instant', $start);
        }

        $attempts = 0;
        while ((time() - $start) < $timeoutSeconds) {
            $attempts++;
            $status = $this->get_order($orderId);

            if (!empty($status['messages']) || !empty($status['sms_code']) || !empty($status['sms_text'])) {
                return $this->buildWaitSuccess($orderId, $status, 'polling', $start, $attempts);
            }
            if (in_array($status['status'] ?? '', ['cancelled', 'failed'], true)) {
                throw new VirtualSMSException("Order {$orderId} was {$status['status']} before SMS arrived.");
            }

            $remaining = $timeoutSeconds - (time() - $start);
            if ($remaining <= 0) {
                break;
            }
            sleep(max(1, min($intervalSeconds, $remaining)));
        }

        return [
            'success' => false,
            'error' => 'timeout',
            'message' => "No SMS received within {$timeoutSeconds} seconds.",
            'order_id' => $orderId,
            'phone_number' => $initial['phone_number'] ?? null,
            'tip' => 'Call get_sms with this order_id later to check, or cancel_order to refund.',
        ];
    }

    private function buildWaitSuccess(string $orderId, array $order, string $deliveryMethod, int $start, ?int $pollAttempts = null): array
    {
        $out = [
            'success' => true,
            'order_id' => $orderId,
            'phone_number' => $order['phone_number'] ?? null,
            'status' => 'sms_received',
            'messages' => $order['messages'] ?? [],
            'code' => $order['code'] ?? null,
            'delivery_method' => $deliveryMethod,
            'elapsed_seconds' => time() - $start,
        ];
        if ($pollAttempts !== null) {
            $out['poll_attempts'] = $pollAttempts;
        }
        return $out;
    }

    /**
     * Cancel + refund an order (before any SMS received). Pre-checks
     * cancel_available_at from a fresh get_order() and short-circuits with a
     * local cooldown_active error if still in the future (120s post-purchase
     * cooldown) — saves a round-trip. Best-effort: if the pre-check lookup
     * fails, the backend enforces the cooldown anyway.
     */
    public function cancel_order(string $orderId): array
    {
        try {
            $order = $this->get_order($orderId);
            $blocked = $this->preCheckCooldown($order['cancel_available_at'] ?? null, 'cancel');
            if ($blocked !== null) {
                return $blocked;
            }
        } catch (\Throwable $e) {
            // Lookup failed. Let the backend handle it.
        }
        return $this->request('POST', '/api/v1/customer/cancel/' . rawurlencode($orderId));
    }

    /** Get a new number for the same service/country, no extra charge. Same cooldown pre-check pattern as cancel_order(). */
    public function swap_number(string $orderId): array
    {
        try {
            $order = $this->get_order($orderId);
            $blocked = $this->preCheckCooldown($order['swap_available_at'] ?? null, 'swap');
            if ($blocked !== null) {
                return $blocked;
            }
        } catch (\Throwable $e) {
            // Lookup failed. Let the backend handle it.
        }
        return $this->normalizeOrder($this->request('POST', '/api/v1/customer/swap/' . rawurlencode($orderId)));
    }

    private function preCheckCooldown(?string $availableAt, string $action): ?array
    {
        if ($availableAt === null || $availableAt === '') {
            return null;
        }
        $availableMs = strtotime($availableAt);
        if ($availableMs === false) {
            return null;
        }
        $now = time();
        if ($now >= $availableMs) {
            return null;
        }
        $waitSeconds = $availableMs - $now;
        return [
            'error' => 'cooldown_active',
            'action' => $action,
            'message' => ucfirst($action) . " cooldown active. Try again in {$waitSeconds} seconds.",
            'retry_at' => $availableAt,
            'wait_seconds' => $waitSeconds,
        ];
    }

    /** Ask the current provider to resend the SMS to the SAME number (use swap_number for a new number). */
    public function retry_order(string $orderId): array
    {
        return $this->request('POST', '/api/v1/orders/' . rawurlencode($orderId) . '/retry');
    }

    /**
     * List orders, optional status filter. A 404 on this endpoint is
     * swallowed to [] (older deployments may not have it), not raised.
     */
    public function list_orders(?string $status = null): array
    {
        try {
            $query = $status !== null ? ['status' => $status] : [];
            $res = $this->request('GET', '/api/v1/customer/orders', $query);
            $raw = $res['orders'] ?? (array_keys($res) === range(0, count($res) - 1) ? $res : []);
            $out = [];
            foreach ((array) $raw as $o) {
                $out[] = [
                    'order_id' => (string) ($o['order_id'] ?? $o['id'] ?? ''),
                    'phone_number' => (string) ($o['phone_number'] ?? ''),
                    'service' => (string) ($o['service_id'] ?? $o['service'] ?? ''),
                    'country' => (string) ($o['country_id'] ?? $o['country'] ?? ''),
                    'price' => (float) ($o['price_charged'] ?? $o['price'] ?? 0),
                    'created_at' => isset($o['created_at']) ? (string) $o['created_at'] : null,
                    'expires_at' => isset($o['expires_at']) ? (string) $o['expires_at'] : null,
                    'status' => (string) ($o['status'] ?? ''),
                    'sms_code' => isset($o['sms_code']) ? (string) $o['sms_code'] : null,
                    'sms_text' => isset($o['sms_text']) ? (string) $o['sms_text'] : null,
                ];
            }
            return $out;
        } catch (NotFoundException $e) {
            return [];
        }
    }

    /** Order history with client-side filtering (service/country/since_days) + a hard cap of 50. */
    public function order_history(array $options = []): array
    {
        $limit = min(50, (int) ($options['limit'] ?? 20));
        $orders = $this->list_orders($options['status'] ?? null);

        $cutoff = isset($options['since_days']) ? time() - ((int) $options['since_days'] * 86400) : null;
        $serviceFilter = isset($options['service']) ? strtolower($options['service']) : null;
        $countryFilter = isset($options['country']) ? strtoupper($options['country']) : null;

        $filtered = array_values(array_filter($orders, function ($o) use ($cutoff, $serviceFilter, $countryFilter) {
            if ($cutoff !== null) {
                $ts = !empty($o['created_at']) ? strtotime($o['created_at']) : false;
                if ($ts === false || $ts < $cutoff) {
                    return false;
                }
            }
            if ($serviceFilter !== null && strtolower((string) ($o['service'] ?? '')) !== $serviceFilter) {
                return false;
            }
            if ($countryFilter !== null && strtoupper((string) ($o['country'] ?? '')) !== $countryFilter) {
                return false;
            }
            return true;
        }));

        $capped = array_slice($filtered, 0, $limit);

        return [
            'count' => count($capped),
            'total_matched' => count($filtered),
            'filters' => [
                'status' => $options['status'] ?? null,
                'service' => $options['service'] ?? null,
                'country' => $options['country'] ?? null,
                'since_days' => $options['since_days'] ?? null,
            ],
            'orders' => $capped,
        ];
    }

    /**
     * Bulk-cancel every active order. Fans out cancel_order() per id with
     * gather-with-partial-failure (never abort-on-first-error) — the PHP
     * equivalent of Promise.allSettled.
     */
    public function cancel_all_orders(): array
    {
        $activeStatuses = ['waiting', 'pending', 'sms_received', 'created'];
        $orders = $this->list_orders();
        $active = array_values(array_filter($orders, static fn ($o) => in_array($o['status'] ?? '', $activeStatuses, true)));

        if (empty($active)) {
            return ['cancelled' => 0, 'failed' => 0, 'total_active' => 0, 'cancelled_orders' => [], 'failures' => [], 'message' => 'No active orders to cancel.'];
        }

        $cancelledOrders = [];
        $failures = [];
        foreach ($active as $o) {
            try {
                $res = $this->cancel_order($o['order_id']);
                $cancelledOrders[] = ['order_id' => $o['order_id'], 'refunded' => (bool) ($res['refunded'] ?? false)];
            } catch (\Throwable $e) {
                $failures[] = ['order_id' => $o['order_id'], 'error' => $e->getMessage()];
            }
        }

        return [
            'cancelled' => count($cancelledOrders),
            'failed' => count($failures),
            'total_active' => count($active),
            'cancelled_orders' => $cancelledOrders,
            'failures' => $failures,
        ];
    }

    /**
     * Find the right service code using natural language ("uber", "binance",
     * "steam"). Client-side fuzzy match over list_services(): exact=1.0,
     * prefix=0.9, substring=0.7, else token-overlap ratio capped at 0.6.
     * Only scores >= 0.5 are returned, top 5.
     */
    public function search_services(string $query): array
    {
        $services = $this->list_services();
        $q = strtolower(trim($query));

        $scored = [];
        foreach ($services as $s) {
            $name = strtolower($s['name']);
            $code = strtolower($s['code']);
            $score = 0.0;

            if ($code === $q || $name === $q) {
                $score = 1.0;
            } elseif (strpos($code, $q) === 0 || strpos($name, $q) === 0) {
                $score = 0.9;
            } elseif (strpos($code, $q) !== false || strpos($name, $q) !== false) {
                $score = 0.7;
            } else {
                $queryTokens = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $nameTokens = preg_split('/[\s_-]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $matches = 0;
                foreach ($queryTokens as $qt) {
                    foreach ($nameTokens as $nt) {
                        if (strpos($nt, $qt) !== false || strpos($qt, $nt) !== false) {
                            $matches++;
                            break;
                        }
                    }
                }
                if ($matches > 0) {
                    $score = ($matches / max(count($queryTokens), count($nameTokens), 1)) * 0.6;
                }
            }

            $scored[] = ['code' => $s['code'], 'name' => $s['name'], 'match_score' => round($score, 2)];
        }

        usort($scored, static fn ($a, $b) => $b['match_score'] <=> $a['match_score']);
        $matches = array_slice(array_values(array_filter($scored, static fn ($s) => $s['match_score'] >= 0.5)), 0, 5);

        if (empty($matches)) {
            return [
                'query' => $query,
                'matches' => [],
                'message' => 'No matching services found',
                'tip' => 'Try list_services to browse all available services.',
            ];
        }

        return [
            'query' => $query,
            'matches' => $matches,
            'tip' => 'Use the "code" field as the service parameter in other methods.',
        ];
    }

    /**
     * Find the cheapest in-stock countries for a service, sorted by price.
     * Uses the same catalog source get_price() uses for real stock — NOT a
     * fan-out over /api/v1/price per country, which has no stock field.
     */
    public function find_cheapest(string $service, int $limit = 5): array
    {
        $catalog = $this->get_catalog_countries($service);
        $results = array_values(array_filter($catalog, static fn ($c) => $c['count'] > 0));
        usort($results, static fn ($a, $b) => $a['price_usd'] <=> $b['price_usd']);

        $top = array_map(static fn ($c) => [
            'country' => $c['iso'],
            'country_name' => $c['name'],
            'price_usd' => $c['price_usd'],
            'stock' => true,
        ], array_slice($results, 0, $limit));

        if (empty($top)) {
            return [
                'service' => $service,
                'cheapest_options' => [],
                'total_available_countries' => 0,
                'message' => "No countries available for service \"{$service}\". Use search_services to verify the service code, or list_services to see all available services.",
            ];
        }

        return [
            'service' => $service,
            'cheapest_options' => $top,
            'total_available_countries' => count($results),
        ];
    }
}
