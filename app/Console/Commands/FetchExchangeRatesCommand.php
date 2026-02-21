<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class FetchExchangeRatesCommand extends Command
{
    protected $signature = 'invoices:fetch-exchange-rates';

    protected $description = 'Fetch and cache common exchange rates for today';

    public function handle(ExchangeRateService $service): int
    {
        $service->fetchCommonRates();

        $this->info('Exchange rates fetched successfully.');

        return self::SUCCESS;
    }
}
