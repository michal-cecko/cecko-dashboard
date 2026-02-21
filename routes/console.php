<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:check-overdue')->daily();
Schedule::command('invoices:fetch-exchange-rates')->daily();
