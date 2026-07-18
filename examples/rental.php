<?php

/**
 * Rental flow: list available countries for the Full Access tier, create a
 * 24h rental, check it, then cancel it (full refund is only available
 * within 20 minutes of purchase and before any SMS has arrived).
 *
 * Run: php examples/rental.php
 */

require __DIR__ . '/../vendor/autoload.php';

use VirtualSMS\VirtualSMS;

$client = new VirtualSMS(getenv('VIRTUALSMS_API_KEY') ?: 'vsms_your_api_key');

// Full Access tier: local SIM inventory, any service, longer durations.
$availability = $client->rentals_available(['tier' => 'full_access']);
$countries = $availability['full_access_countries'] ?? $availability['countries'] ?? [];
if (empty($countries)) {
    exit("No Full Access countries available right now.\n");
}
$country = $countries[0]['country_code'];
echo "Renting in: {$country}\n";

$rental = $client->create_rental([
    'tier' => 'full_access',
    'country' => $country,
    'rental_type' => 'full',
    'duration_hours' => 24,
]);
echo "Rental created: {$rental['rental_id']} -> {$rental['phone_number']}\n";

$fetched = $client->get_rental($rental['rental_id']);
echo 'Status: ' . ($fetched['status'] ?? 'unknown') . "\n";

// Cancel for a full refund (only within the 20-minute window, before any SMS).
$cancelResult = $client->cancel_rental($rental['rental_id']);
echo 'Cancel result: ' . json_encode($cancelResult) . "\n";

// --- Platform tier example (sourced via our global supplier network,
// locked to ONE service, 24/72/168h durations only) ---
$platformAvailability = $client->rentals_available(['tier' => 'platform', 'service' => 'tg']);
$platformCountries = $platformAvailability['countries'] ?? [];
if (!empty($platformCountries)) {
    $platformCountry = $platformCountries[0]['country_code'];
    $platformRental = $client->create_rental([
        'tier' => 'platform',
        'service' => 'tg',
        'country' => $platformCountry,
        'duration_hours' => 24,
    ]);
    echo "Platform-tier rental: {$platformRental['rental_id']} -> {$platformRental['phone_number']}\n";
}
