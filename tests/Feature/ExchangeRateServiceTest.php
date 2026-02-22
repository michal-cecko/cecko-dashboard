<?php

namespace Tests\Feature;

use App\Models\Invoices\ExchangeRate;
use App\Services\Invoices\ExchangeRateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExchangeRateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExchangeRateService;
    }

    public function test_same_currency_returns_one(): void
    {
        $rate = $this->service->getRate('EUR', 'EUR');

        $this->assertEquals(1.0, $rate);
    }

    public function test_returns_cached_rate_from_database(): void
    {
        ExchangeRate::create([
            'base_currency' => 'EUR',
            'target_currency' => 'CZK',
            'rate' => 25.12,
            'date' => now()->toDateString(),
            'source' => 'cnb',
        ]);

        $rate = $this->service->getRate('EUR', 'CZK');

        $this->assertEquals(25.12, $rate);
    }

    public function test_fetches_from_cnb_api_when_not_cached(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response([
                'rates' => [
                    ['currencyCode' => 'EUR', 'rate' => 25.12, 'amount' => 1],
                    ['currencyCode' => 'USD', 'rate' => 23.45, 'amount' => 1],
                ],
            ]),
        ]);

        $rate = $this->service->getRate('EUR', 'CZK', Carbon::today());

        $this->assertEquals(25.12, $rate);

        $this->assertDatabaseHas('exchange_rates', [
            'base_currency' => 'EUR',
            'target_currency' => 'CZK',
            'date' => Carbon::today()->toDateString(),
        ]);
    }

    public function test_cross_rate_calculation(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response([
                'rates' => [
                    ['currencyCode' => 'EUR', 'rate' => 25.0, 'amount' => 1],
                    ['currencyCode' => 'USD', 'rate' => 20.0, 'amount' => 1],
                ],
            ]),
        ]);

        $rate = $this->service->getRate('EUR', 'USD', Carbon::today());

        // EUR→CZK = 25.0, USD→CZK = 20.0, so EUR→USD = 25.0 / 20.0 = 1.25
        $this->assertEquals(1.25, $rate);
    }

    public function test_normalizes_amount_based_rates(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response([
                'rates' => [
                    ['currencyCode' => 'HUF', 'rate' => 6.45, 'amount' => 100],
                ],
            ]),
        ]);

        $rate = $this->service->getRate('HUF', 'CZK', Carbon::today());

        // 100 HUF = 6.45 CZK → 1 HUF = 0.0645 CZK
        $this->assertEqualsWithDelta(0.0645, $rate, 0.0001);
    }

    public function test_returns_null_on_api_failure(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response('Server Error', 500),
        ]);

        $rate = $this->service->getRate('EUR', 'CZK', Carbon::today());

        $this->assertNull($rate);
    }

    public function test_returns_null_for_unknown_currency(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response([
                'rates' => [
                    ['currencyCode' => 'EUR', 'rate' => 25.12, 'amount' => 1],
                ],
            ]),
        ]);

        $rate = $this->service->getRate('XYZ', 'CZK', Carbon::today());

        $this->assertNull($rate);
    }

    public function test_fetch_common_rates_stores_multiple_currencies(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response([
                'rates' => [
                    ['currencyCode' => 'EUR', 'rate' => 25.12, 'amount' => 1],
                    ['currencyCode' => 'USD', 'rate' => 23.45, 'amount' => 1],
                    ['currencyCode' => 'GBP', 'rate' => 29.80, 'amount' => 1],
                ],
            ]),
        ]);

        $this->service->fetchCommonRates('CZK', ['EUR', 'USD', 'GBP']);

        $this->assertDatabaseHas('exchange_rates', ['base_currency' => 'EUR', 'target_currency' => 'CZK']);
        $this->assertDatabaseHas('exchange_rates', ['base_currency' => 'USD', 'target_currency' => 'CZK']);
        $this->assertDatabaseHas('exchange_rates', ['base_currency' => 'GBP', 'target_currency' => 'CZK']);
    }

    public function test_does_not_duplicate_existing_rates(): void
    {
        Http::fake([
            'api.cnb.cz/*' => Http::response([
                'rates' => [
                    ['currencyCode' => 'EUR', 'rate' => 25.50, 'amount' => 1],
                ],
            ]),
        ]);

        $this->service->fetchAndStore('EUR', 'CZK', Carbon::today());
        $this->service->fetchAndStore('EUR', 'CZK', Carbon::today());

        $this->assertEquals(1, ExchangeRate::query()
            ->where('base_currency', 'EUR')
            ->where('target_currency', 'CZK')
            ->where('date', Carbon::today()->toDateString())
            ->count());
    }
}
