# VirtualSMS PHP SDK

PHP client for [VirtualSMS](https://virtualsms.io) — SMS verification using real physical SIM cards.

Unlike VoIP-based services, VirtualSMS uses real SIM cards in hardware modems connected to European and US cellular networks. Near-100% delivery rates on WhatsApp, Telegram, and platforms that block virtual numbers.

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

## API Methods

- `getBalance()` — Account balance in USD
- `getNumber($service, $country)` — Get a phone number
- `getStatus($activationId)` — Check SMS status
- `waitForCode($activationId)` — Auto-poll until code arrives
- `done($activationId)` — Mark complete
- `cancel($activationId)` — Cancel and refund

## Migrating from DaisySMS?

```php
// Change one line:
$client = new VirtualSMS('your_key'); // defaults to virtualsms.io
```

Full [migration guide](https://virtualsms.io/daisysms-alternative).

## Links

- **Website:** [virtualsms.io](https://virtualsms.io)
- **API Docs:** [virtualsms.io/api](https://virtualsms.io/api)
- **Pricing:** [virtualsms.io/pricing](https://virtualsms.io/pricing)
- **Python SDK:** [pypi.org/project/virtualsms](https://pypi.org/project/virtualsms/)
- **Node.js SDK:** [npmjs.com/package/virtualsms-sdk](https://www.npmjs.com/package/virtualsms-sdk)
- **GitHub:** [github.com/virtualsms-io](https://github.com/virtualsms-io)

## License

MIT
