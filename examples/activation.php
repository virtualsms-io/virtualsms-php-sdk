<?php

/**
 * Basic activation flow: get a number for SMS verification, wait for the
 * code, then read the balance to confirm the charge.
 *
 * Run: php examples/activation.php
 */

require __DIR__ . '/../vendor/autoload.php';

use VirtualSMS\VirtualSMS;

// Get your API key at https://virtualsms.io (Settings -> API Keys)
$client = new VirtualSMS(getenv('VIRTUALSMS_API_KEY') ?: 'vsms_your_api_key');

// Find the right service code from a natural-language query.
$found = $client->search_services('telegram');
$serviceCode = $found['matches'][0]['code'] ?? 'tg';
echo "Using service code: {$serviceCode}\n";

// Find the cheapest in-stock country for that service.
$cheapest = $client->find_cheapest($serviceCode, 3);
$country = $cheapest['cheapest_options'][0]['country'] ?? 'US';
echo "Cheapest available country: {$country}\n";

// Confirm price + real stock before buying.
$price = $client->get_price($serviceCode, $country);
if (!$price['available']) {
    exit("No stock for {$serviceCode}/{$country} right now.\n");
}
echo "Price: \${$price['price_usd']} {$price['currency']}\n";

// Buy the number.
$order = $client->create_order($serviceCode, $country);
echo "Number: {$order['phone_number']} (order {$order['order_id']})\n";

// Block until the SMS arrives (default timeout 300s, poll every 5s).
$result = $client->wait_for_sms($order['order_id'], 120, 5);

if ($result['success']) {
    echo "Code received: {$result['code']}\n";
} else {
    echo "Timed out waiting for SMS. Call get_sms('{$order['order_id']}') later, or cancel_order to refund.\n";
    $client->cancel_order($order['order_id']);
}

$balance = $client->get_balance();
echo "Remaining balance: \${$balance['balance_usd']}\n";
