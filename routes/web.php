<?php

use App\Http\Controllers\Toolkit\FileShareController;
use App\Http\Controllers\Toolkit\GalleryShareController;
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

Route::get('/gallery/{token}', [GalleryShareController::class, 'show'])
    ->name('gallery.public')
    ->where('token', '[0-9a-f-]{36}');

Route::get('/gallery/{token}/download', [GalleryShareController::class, 'downloadAll'])
    ->name('gallery.download-all')
    ->where('token', '[0-9a-f-]{36}');

Route::get('/file-share/{token}', [FileShareController::class, 'show'])
    ->name('file-share.public')
    ->where('token', '[0-9a-f-]{36}');

Route::get('/file-share/{token}/download/{media}', [FileShareController::class, 'download'])
    ->name('file-share.download')
    ->where(['token' => '[0-9a-f-]{36}', 'media' => '[0-9]+']);
