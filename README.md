# VirtualSMS PHP SDK

## What is VirtualSMS?

Official PHP SDK for the VirtualSMS API. VirtualSMS is an account verification platform for
individuals, developers, and AI agents: one-time SMS verification, dedicated number rentals,
matching-country proxies, and private cloud browser sessions (beta), all behind one API, one
MCP server, and one prepaid balance. This SDK wraps the REST API in native PHP, backed by real
carrier-issued mobile numbers (real physical SIM cards, not VoIP) across 2500+ services in
145+ countries.

Built for developers and AI agents: REST API, hosted MCP server, SDKs.

## Installation

```bash
composer require virtualsms/sdk
```

Requires PHP 7.4+ with the `curl` and `json` extensions (bundled by default in almost every PHP install).

## Quick Start

<!-- TODO: re-point to /dashboard once the frontend migration ships -->

```php
use VirtualSMS\VirtualSMS;

// 1. Get your API key at https://virtualsms.io (Settings -> API Keys)
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

## Capabilities

1. One-time SMS verification. Receive a code for a service like WhatsApp, Telegram, Discord,
   or a dating app, on demand, from $0.05 per code.
2. Dedicated number rentals. Hold one number for 1-30 days and receive SMS from any service
   on that number, from $0.25/day.
3. Matching-country proxies. Pair a number with an IP from the same country, across 223
   proxy countries, from $1.10/GB.
4. Private cloud browser sessions (beta). Start a country-matched browser in a live viewer
   for the signup step itself, invite-only.

## Why real SIM cards

VirtualSMS runs on real carrier-issued mobile numbers, backed by real physical SIM cards,
not VoIP. Services like WhatsApp, Telegram, Discord, and dating apps run a carrier lookup
before they send a code, and VoIP or virtual numbers fail that check more often than a real
SIM does. A physical SIM on a real carrier network reads like any other phone on that network,
carriers like Vodafone, O2, and T-Mobile depending on the country, which is part of why
VirtualSMS holds a 95%+ success rate across 2500+ services in 145+ countries.

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

## AI agents and MCP

This SDK is the API-client half: a typed wrapper an application or agent framework calls
directly. The hosted MCP server is the separate agent-facing half, exposing the same
capabilities as MCP tools for MCP-compatible clients like Claude and Cursor. Use this SDK when
you're writing code that calls VirtualSMS; use the MCP server when an AI agent needs to call
VirtualSMS itself without a code layer in between.

## FAQ

### What is VirtualSMS?

VirtualSMS is an account verification platform for individuals, developers, and AI agents. It combines one-time SMS verification, dedicated number rentals, matching-country proxies, and private cloud browser sessions behind one API, one MCP server, and one prepaid balance.

### Does VirtualSMS use real SIM cards or VoIP numbers?

VirtualSMS uses real carrier-issued mobile numbers, backed by real physical SIM cards, not VoIP. Many services, including WhatsApp, Telegram, Discord, and dating apps, reject VoIP and virtual numbers at signup; a real physical SIM on a real carrier network passes that check far more often, which is reflected in a 95%+ success rate.

### Which services and countries does VirtualSMS support?

VirtualSMS covers 2500+ services across 145+ countries for SMS verification and number rentals, plus matching-country proxies across 223 proxy countries. Coverage spans messaging apps, social platforms, marketplaces, dating apps, and financial services.

### Can I rent a number, or only buy one-time codes?

Both. Buy a single one-time code from $0.05, or rent a dedicated number for 1-30 days from $0.25/day to receive SMS from any service on that number for the rental window.

### Does VirtualSMS work with AI agents and MCP?

Yes. VirtualSMS exposes a hosted MCP server plus a REST API and official SDKs in nine languages, so an AI agent can request a number, wait for a code, or manage a rental the same way a developer would call the API directly.

### How much does VirtualSMS cost?

Pricing is pay-as-you-go from one prepaid balance: SMS verification from $0.05 per code, number rentals from $0.25/day, and proxies from $1.10/GB. There is no subscription requirement.

### Is there a free API key?

Yes. Creating a VirtualSMS account issues an API key immediately, at no cost. You only spend from your prepaid balance when you place an order: an activation, a rental, or a proxy.

## Links

- **Homepage:** [virtualsms.io](https://virtualsms.io)
- **Docs:** [virtualsms.io/docs](https://virtualsms.io/docs)
- **MCP server:** [virtualsms.io/mcp](https://virtualsms.io/mcp)
- **Pricing:** [virtualsms.io/pricing](https://virtualsms.io/pricing)
- **REST API:** [virtualsms.io/api/v1](https://virtualsms.io/api/v1)
- **Python SDK:** [pypi.org/project/virtualsms](https://pypi.org/project/virtualsms/)
- **Node.js SDK:** [npmjs.com/package/virtualsms-sdk](https://www.npmjs.com/package/virtualsms-sdk)
- **Other SDKs:** [Ruby](https://rubygems.org/gems/virtualsms-sdk) · [.NET](https://www.nuget.org/packages/VirtualSMS) · [Go](https://pkg.go.dev/github.com/virtualsms-io/go-sdk) · [Rust](https://crates.io/crates/virtualsms) · [Swift](https://github.com/virtualsms-io/swift-sdk) · [Java](https://central.sonatype.com/artifact/io.virtualsms/virtualsms-sdk)
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
