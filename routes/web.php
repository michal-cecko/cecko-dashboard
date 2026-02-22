<?php

use App\Models\Invoices\Invoice;
use App\Services\Invoices\InvoicePdfService;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(route('filament.songs.home'));
});

Route::get('/faktury/preview/{invoice}', function (Invoice $invoice) {
    abort_unless(auth()->user()->can('view', $invoice), 403);

    $locale = request()->query('locale', $invoice->company->default_locale ?? 'sk');

    return app(InvoicePdfService::class)->generateHtml($invoice, $locale);
})->middleware(['web', 'auth'])->name('invoices.preview');
