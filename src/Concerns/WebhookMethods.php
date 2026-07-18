<?php

namespace VirtualSMS\Concerns;

/**
 * Webhooks (7 methods) — new in v2.0.0. Base path
 * /api/v1/customer/webhooks, same X-API-Key auth as every other customer
 * route (customer-webhooks.js authenticate middleware mirrors ws-gateway).
 */
trait WebhookMethods
{
    /** List the account's webhook subscriptions. */
    public function list_webhooks(): array
    {
        return $this->request('GET', '/api/v1/customer/webhooks');
    }

    /**
     * Create a webhook subscription. `secret` is returned exactly once, on
     * create only — store it immediately, it is never shown again.
     *
     * @param array{url:string, description?:string, events:array<int,string>, threshold?:float} $params
     */
    public function create_webhook(array $params): array
    {
        return $this->request('POST', '/api/v1/customer/webhooks', [], $params);
    }

    /** Get one webhook (no secret). */
    public function get_webhook(string $id): array
    {
        return $this->request('GET', '/api/v1/customer/webhooks/' . rawurlencode($id));
    }

    /**
     * Partial update (url/description/events/threshold/active/paused). At
     * least one field is required. Un-pausing (paused:false when previously
     * true) resets failure_count_consecutive to 0 server-side.
     */
    public function update_webhook(string $id, array $fields): array
    {
        return $this->request('PATCH', '/api/v1/customer/webhooks/' . rawurlencode($id), [], $fields);
    }

    /** Delete a webhook. */
    public function delete_webhook(string $id): array
    {
        return $this->request('DELETE', '/api/v1/customer/webhooks/' . rawurlencode($id));
    }

    /** Fire a synthetic test event through the real dispatcher. Requires the webhook to be active and not paused. */
    public function test_webhook(string $id): array
    {
        return $this->request('POST', '/api/v1/customer/webhooks/' . rawurlencode($id) . '/test', [], []);
    }

    /** List recent delivery attempts for a webhook. */
    public function list_webhook_deliveries(string $id, int $limit = 100, int $offset = 0): array
    {
        return $this->request('GET', '/api/v1/customer/webhooks/' . rawurlencode($id) . '/deliveries', [
            'limit' => min(500, $limit),
            'offset' => $offset,
        ]);
    }
}
