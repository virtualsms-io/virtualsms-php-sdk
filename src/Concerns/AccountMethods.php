<?php

namespace VirtualSMS\Concerns;

/**
 * Account (4 methods): balance, full profile, paginated transactions, and
 * the client-side get_stats() aggregation helper.
 */
trait AccountMethods
{
    /** Check account balance. */
    public function get_balance(): array
    {
        $raw = $this->request('GET', '/api/v1/customer/balance');
        return ['balance_usd' => (float) ($raw['balance_usd'] ?? $raw['balance'] ?? 0)];
    }

    /** Full account profile. */
    public function get_profile(): array
    {
        $raw = $this->request('GET', '/api/v1/customer/profile');
        return [
            'id' => (string) ($raw['id'] ?? ''),
            'email' => (string) ($raw['email'] ?? ''),
            'telegram_linked' => (bool) ($raw['telegram_linked'] ?? false),
            'telegram_username' => isset($raw['telegram_username']) ? (string) $raw['telegram_username'] : null,
            'balance_usd' => (float) ($raw['balance_usd'] ?? 0),
            'total_spent_usd' => (float) ($raw['total_spent_usd'] ?? 0),
            'total_credits_usd' => (float) ($raw['total_credits_usd'] ?? 0),
            'total_orders' => (int) ($raw['total_orders'] ?? 0),
            'active_api_keys' => (int) ($raw['active_api_keys'] ?? 0),
            'created_at' => (string) ($raw['created_at'] ?? ''),
        ];
    }

    /**
     * Paginated transaction history.
     *
     * @param array{type?:string, from?:string, to?:string, limit?:int, offset?:int} $options
     */
    public function get_transactions(array $options = []): array
    {
        $raw = $this->request('GET', '/api/v1/customer/transactions', [
            'type' => $options['type'] ?? null,
            'from' => $options['from'] ?? null,
            'to' => $options['to'] ?? null,
            'limit' => $options['limit'] ?? null,
            'offset' => $options['offset'] ?? null,
        ]);
        $items = (array) ($raw['transactions'] ?? []);
        $transactions = [];
        foreach ($items as $t) {
            $transactions[] = [
                'id' => (string) ($t['id'] ?? ''),
                'amount' => (float) ($t['amount'] ?? 0),
                'type' => (string) ($t['type'] ?? ''),
                'description' => isset($t['description']) ? (string) $t['description'] : null,
                'order_id' => isset($t['order_id']) ? (string) $t['order_id'] : null,
                'balance_before' => (float) ($t['balance_before'] ?? 0),
                'balance_after' => (float) ($t['balance_after'] ?? 0),
                'created_at' => (string) ($t['created_at'] ?? ''),
            ];
        }
        return [
            'count' => (int) ($raw['count'] ?? count($transactions)),
            'limit' => (int) ($raw['limit'] ?? 0),
            'offset' => (int) ($raw['offset'] ?? 0),
            'transactions' => $transactions,
        ];
    }

    /**
     * Aggregated usage stats over a lookback window. Client-side: calls
     * get_balance() + list_orders(), then aggregates locally (status/service/
     * country breakdowns, spend excluding cancelled orders, success rate over
     * terminal-state orders only). Warns when the 50-row server cap on
     * list_orders() may be undercounting.
     */
    public function get_stats(int $sinceDays = 30): array
    {
        $cutoff = time() - ($sinceDays * 86400);
        $balance = $this->get_balance();
        $orders = $this->list_orders();

        $inWindow = array_values(array_filter($orders, function ($o) use ($cutoff) {
            $ts = !empty($o['created_at']) ? strtotime($o['created_at']) : false;
            return $ts !== false && $ts >= $cutoff;
        }));

        $byStatus = [];
        $byService = [];
        $byCountry = [];
        $totalSpend = 0.0;
        $successful = 0;
        $terminal = 0;
        $terminalStatuses = ['completed', 'sms_received', 'expired', 'cancelled'];

        foreach ($inWindow as $o) {
            $status = $o['status'] ?? '';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            if (!empty($o['service'])) {
                $byService[$o['service']] = ($byService[$o['service']] ?? 0) + 1;
            }
            if (!empty($o['country'])) {
                $byCountry[$o['country']] = ($byCountry[$o['country']] ?? 0) + 1;
            }
            if ($status !== 'cancelled' && is_numeric($o['price'] ?? null)) {
                $totalSpend += (float) $o['price'];
            }
            if (in_array($status, $terminalStatuses, true)) {
                $terminal++;
                if (in_array($status, ['completed', 'sms_received'], true)) {
                    $successful++;
                }
            }
        }

        $topEntries = static function (array $rec, int $n = 5): array {
            arsort($rec);
            $out = [];
            foreach (array_slice($rec, 0, $n, true) as $key => $count) {
                $out[] = ['key' => $key, 'count' => $count];
            }
            return $out;
        };

        return [
            'window_days' => $sinceDays,
            'balance_usd' => $balance['balance_usd'],
            'total_orders' => count($inWindow),
            'successful_orders' => $successful,
            'success_rate' => $terminal > 0 ? round(($successful / $terminal) * 1000) / 10 : null,
            'total_spend_usd' => round($totalSpend, 2),
            'status_breakdown' => $byStatus,
            'top_services' => $topEntries($byService),
            'top_countries' => $topEntries($byCountry),
            'note' => count($orders) >= 50
                ? 'Server caps order history at 50 rows. Stats may undercount if your activity exceeds 50 orders in the window.'
                : null,
        ];
    }
}
