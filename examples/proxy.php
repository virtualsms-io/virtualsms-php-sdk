<?php

/**
 * Proxy flow: browse the catalog, buy GB of residential proxy traffic,
 * generate a ready-to-use connection string, then rotate the exit IP.
 *
 * Run: php examples/proxy.php
 */

require __DIR__ . '/../vendor/autoload.php';

use VirtualSMS\VirtualSMS;

$client = new VirtualSMS(getenv('VIRTUALSMS_API_KEY') ?: 'vsms_your_api_key');

$catalog = $client->list_proxy_catalog();
if (empty($catalog)) {
    exit("No proxy pool types available.\n");
}
$poolType = $catalog[0]['id'];
echo "Pool type: {$poolType} (\${$catalog[0]['price_per_gb']}/GB)\n";

$purchase = $client->buy_proxy([
    'pool_type' => $poolType,
    'gb' => 1,
]);
echo "Bought proxy {$purchase['proxy_id']}: {$purchase['gb_added']}GB\n";

// Compose a ready-to-use connection string (no extra backend call).
$endpoint = $client->generate_proxy_endpoint([
    'proxy_id' => $purchase['proxy_id'],
    'country_code' => $purchase['country_code'],
    'protocol' => 'HTTP',
    'format' => 'host:port:user:pass',
]);
echo 'Endpoint: ' . $endpoint['endpoints'][0] . "\n";

// Check usage, then rotate the exit IP.
$usage = $client->get_proxy_usage($purchase['proxy_id']);
echo "Used: {$usage['gb_used']}GB / remaining: {$usage['gb_remaining']}GB\n";

$rotated = $client->rotate_proxy($purchase['proxy_id']);
echo 'Rotated: ' . json_encode($rotated) . "\n";
