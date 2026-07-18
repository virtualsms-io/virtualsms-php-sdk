# VirtualSMS PHP SDK

Native PHP client for the **VirtualSMS REST API v1**: real carrier mobile numbers (not VoIP), number rentals, and matching-country proxies, all in one typed client.

Built for developers and AI agents: REST API, hosted MCP server, SDKs.

Real physical SIM cards, not VoIP, so codes land on WhatsApp, Telegram, and other platforms that block virtual numbers. 95%+ delivery on real carrier SIMs. Prices are public and live stock is visible before checkout, so what you see is what you pay.

## Installation

```bash
composer require virtualsms/sdk
```

Requires PHP 7.4+ with the `curl` and `json` extensions (bundled by default in almost every PHP install).

## Quick Start

```php
use VirtualSMS\VirtualSMS;

// 1. Get your API key at https://virtualsms.io/dashboard (Settings -> API Keys)
$client = new VirtualSMS('vsms_your_api_key');

// 2. Buy a number for WhatsApp verification
$order = $client->create_order('wa', 'GB'); // service code, ISO country
echo "Use this number: {$order['phone_number']}\n";

// 3. Wait for the verification code
$result = $client->wait_for_sms($order['order_id']);
if ($result['success']) {
    echo "Code: {$result['code']}\n";
}
```

Full docs: [virtualsms.io/docs](https://virtualsms.io/docs).

## What this SDK covers

A native client for the whole VirtualSMS REST v1 surface, 47 methods across seven groups:

| Group | Examples |
|---|---|
| Activations / Orders | `create_order`, `get_order`, `wait_for_sms`, `cancel_order`, `swap_number`, `search_services`, `find_cheapest` |
| Rentals | `create_rental` (Full Access + Platform tiers), `list_rentals`, `extend_rental`, `cancel_rental` |
| Proxies | `list_proxy_catalog`, `buy_proxy`, `rotate_proxy`, `generate_proxy_endpoint` |
| Account | `get_balance`, `get_profile`, `get_transactions`, `get_stats` |
| Session | `start_manual_registration_session` (invite-only beta) |
| Tools | `check_number` |
| Webhooks | `create_webhook`, `list_webhooks`, `update_webhook`, `list_webhook_deliveries` |

See [`examples/`](examples/) for full activation, rental, and proxy flows.

## Error handling

Every failure throws a typed exception under `VirtualSMS\Exceptions`, all extending `VirtualSMSException`:

| Exception | Cause |
|---|---|
| `BadApiKeyException` | Invalid or missing API key (HTTP 401) |
| `InsufficientBalanceException` | Balance too low for the purchase (HTTP 402) |
| `NotFoundException` | Order/rental/proxy/webhook id doesn't exist (HTTP 404) |
| `RateLimitedException` | Too many requests (HTTP 429), never auto-retried |
| `ServerErrorException` | 5xx. `isRetryable()` is `true` only for GET/HEAD calls: a 5xx on a purchase/cancel/rotate call may have completed server-side, so verify with a `list_*`/`get_*` call before retrying |
| `ApiException` | Any other 4xx |

```php
use VirtualSMS\Exceptions\InsufficientBalanceException;

try {
    $client->create_order('wa', 'GB');
} catch (InsufficientBalanceException $e) {
    echo "Top up your balance: {$e->getMessage()}\n";
}
```

GET/HEAD requests are retried automatically (up to 3 total attempts, exponential backoff) on network errors or 5xx. Mutating calls (POST/PUT/PATCH/DELETE) are never auto-retried by this SDK.

## Two rental tiers

Both refund-identical: full refund within 20 minutes of purchase, before the first SMS.

- **Full Access** (`tier: 'full_access'`): local SIM inventory, usable for any service, longer durations.
- **Platform** (`tier: 'platform'`): sourced via our global supplier network, locked to one chosen service per number, 24/72/168h durations only.

```php
$client->create_rental([
    'tier' => 'full_access',
    'country' => 'DE',
    'rental_type' => 'full',
    'duration_hours' => 24,
]);
```

## Publishing

This package is versioned via git tags: pushing a `vX.Y.Z` tag to this repo triggers Packagist's update webhook automatically (no separate publish workflow/token needed on this repo). Packagist always reflects the latest tag.

## Links

- **Homepage:** [virtualsms.io](https://virtualsms.io)
- **Docs:** [virtualsms.io/docs](https://virtualsms.io/docs)
- **MCP server:** [virtualsms.io/mcp](https://virtualsms.io/mcp)
- **Pricing:** [virtualsms.io/pricing](https://virtualsms.io/pricing)
- **REST API:** [virtualsms.io/api/v1](https://virtualsms.io/api/v1)
- **Python SDK:** [pypi.org/project/virtualsms](https://pypi.org/project/virtualsms/)
- **Node.js SDK:** [npmjs.com/package/virtualsms-sdk](https://www.npmjs.com/package/virtualsms-sdk)
- **GitHub:** [github.com/virtualsms-io](https://github.com/virtualsms-io)

### Ecosystem

- Official MCP registry: `io.github.virtualsms-io/sms`
- [VirtualSMS on Glama](https://glama.ai/mcp/servers)
- [VirtualSMS on Smithery](https://smithery.ai/servers/virtualsms/virtualsms-mcp)
- [VirtualSMS on mcp.so](https://mcp.so/servers/mcp-server-virtualsms-io)
- [virtualsms-mcp on npm](https://www.npmjs.com/package/virtualsms-mcp)

## Development

```bash
composer install
composer test          # PHPUnit smoke tests (get_balance, list_services, get_price)
sh scripts/check-positioning.sh   # run before committing copy changes
```

Live smoke tests require a real key: `VIRTUALSMS_TEST_API_KEY=vsms_xxx composer test`. Without it, the smoke tests skip cleanly (a first checkout with no credentials still passes CI's structural checks).

## Changelog

### 2.0.0: Breaking change

First REST v1-native major release. Talks to `https://virtualsms.io/api/v1/*` directly. The previous 1.x line wrapped the legacy `/stubs/handler_api.php` sms-activate-compatible dispatcher (`getBalance`/`getNumber`/`getStatus`/`waitForCode`/`done`/`cancel`); that dispatcher is **not used at all** by 2.x. If you're upgrading from 1.x, method names and the constructor's return shapes have changed, see Quick Start above.

## License

MIT
