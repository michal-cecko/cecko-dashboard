<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:check-overdue')->dailyAt('00:45');
Schedule::command('invoices:fetch-exchange-rates')->dailyAt('00:30');
Schedule::command('toolkit:delete-expired-galleries')->dailyAt('01:00');
Schedule::command('garaz:fetch-forum-knowledge')->weeklyOn(1, '02:30');
