<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:check-overdue')->dailyAt('00:45');
Schedule::command('invoices:fetch-exchange-rates')->dailyAt('00:30');
