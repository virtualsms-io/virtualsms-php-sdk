<?php

namespace VirtualSMS\Tests;

use PHPUnit\Framework\TestCase;
use VirtualSMS\VirtualSMS;

/**
 * Minimum smoke test per the v2.0.0 SDK spec: get_balance + list_services +
 * get_price must succeed against a live account. Requires a real API key via
 * VIRTUALSMS_TEST_API_KEY — skips (not fails) when unset so `composer test`
 * still passes in environments without credentials (e.g. a first local
 * checkout). CI should set VIRTUALSMS_TEST_API_KEY as a secret to get real
 * coverage.
 */
final class SmokeTest extends TestCase
{
    private function client(): VirtualSMS
    {
        $key = getenv('VIRTUALSMS_TEST_API_KEY');
        if ($key === false || $key === '') {
            self::markTestSkipped('VIRTUALSMS_TEST_API_KEY not set — skipping live smoke test.');
        }
        return new VirtualSMS($key);
    }

    public function testGetBalanceReturnsNumericBalance(): void
    {
        $client = $this->client();
        $balance = $client->get_balance();
        self::assertArrayHasKey('balance_usd', $balance);
        self::assertIsFloat($balance['balance_usd']);
    }

    public function testListServicesReturnsNonEmptyArray(): void
    {
        $client = $this->client();
        $services = $client->list_services();
        self::assertIsArray($services);
        self::assertNotEmpty($services);
        self::assertArrayHasKey('code', $services[0]);
        self::assertArrayHasKey('name', $services[0]);
    }

    public function testGetPriceReturnsShapedResult(): void
    {
        $client = $this->client();
        $price = $client->get_price('wa', 'GB');
        self::assertArrayHasKey('price_usd', $price);
        self::assertArrayHasKey('currency', $price);
        self::assertArrayHasKey('available', $price);
        self::assertIsBool($price['available']);
    }

    /**
     * list_countries requires a valid key against the live API (it is not
     * an anonymous/public endpoint despite the name), so this reuses the
     * same skip-when-unset guard as the rest of this suite.
     */
    public function testPublicEndpointsWorkWithoutApiKey(): void
    {
        $client = $this->client();
        $countries = $client->list_countries();
        self::assertIsArray($countries);
    }
}
