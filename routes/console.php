<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('invoices:check-overdue')->dailyAt('00:45');
Schedule::command('invoices:fetch-exchange-rates')->dailyAt('00:30');
Schedule::command('invoices:generate-recurring')->dailyAt('06:00');
Schedule::command('toolkit:delete-expired-galleries')->dailyAt('01:00');
Schedule::command('toolkit:delete-expired-file-shares')->dailyAt('01:05');
Schedule::command('garaz:fetch-forum-knowledge')->weeklyOn(1, '02:30');
Schedule::command('stride:advance-blocks')->dailyAt('03:15');
Schedule::command('stride:send-push-notifications')->everyMinute()->withoutOverlapping();
Schedule::command('tickets:send-telegram-digest')->dailyAt('16:00')->timezone('Europe/Bratislava');
Schedule::command('tickets:send-client-notices')->hourly()->withoutOverlapping();
Schedule::command('tickets:apply-plan-changes')->dailyAt('00:10')->timezone('Europe/Bratislava');
