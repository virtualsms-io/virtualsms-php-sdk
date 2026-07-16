# VirtualSMS PHP SDK

VirtualSMS is an account verification platform that combines real carrier mobile numbers, matching-country proxies and a private cloud browser into one connected workflow.

Built for developers and AI agents: REST API, hosted MCP server, SDKs.

This is the **PHP client for SMS verification** — real carrier numbers, not VoIP. Near-100% delivery on WhatsApp, Telegram, and other platforms that block virtual numbers. Prices are public and live stock is visible before checkout, so what you see is what you pay.

## Installation

```bash
composer require virtualsms/sdk
```

## Quick Start

```php
use VirtualSMS\VirtualSMS;

// Get your API key at https://virtualsms.io (Settings → API Keys)
$client = new VirtualSMS('vsms_your_api_key');

// Check balance
$balance = $client->getBalance();
echo "Balance: \${$balance}\n";

// Get a number for WhatsApp verification
$activation = $client->getNumber('wa', 22); // 22 = UK
echo "Use this number: {$activation->phone}\n";

// Wait for the verification code
$code = $client->waitForCode($activation->activationId);
echo "Verification code: {$code}\n";

// Mark as done
$client->done($activation->activationId);
```

## Service Codes

| Service | Code |
|---------|------|
| WhatsApp | `wa` |
| Telegram | `tg` |
| Google | `go` |
| Instagram | `ig` |
| Facebook | `fb` |
| Discord | `ds` |

700+ services supported. Full list at [virtualsms.io/services](https://virtualsms.io/services).

## What this SDK does

This package wraps the SMS **verification** endpoints only: request a number, poll for the code, mark it done or cancel it. It does not talk to proxies, number rentals, or the cloud browser.

- `getBalance()` — Account balance in USD
- `getNumber($service, $country)` — Get a phone number
- `getStatus($activationId)` — Check SMS status
- `waitForCode($activationId)` — Auto-poll until code arrives
- `done($activationId)` — Mark complete
- `cancel($activationId)` — Cancel and refund

**Need proxies, number rentals, or the cloud browser?** Those are part of the wider VirtualSMS platform but aren't exposed by this SDK yet (roadmap — coming soon). Use them today via:
- The [REST API](https://virtualsms.io/docs) — full platform access: numbers, proxies, cloud browser
- The [hosted MCP server](https://virtualsms.io/mcp) — lets AI agents drive the full platform (numbers, proxies, cloud browser) directly

## Migrating from DaisySMS?

```php
// Change one line:
$client = new VirtualSMS('your_key'); // defaults to virtualsms.io
```

Full [migration guide](https://virtualsms.io/daisysms-alternative).

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

Run `sh scripts/check-positioning.sh` before committing copy changes. It fails on
stale service or country counts and other banned positioning wording.

## License

MIT
