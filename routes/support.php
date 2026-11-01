<?php

use App\Http\Controllers\Support\SupportApiController;
use App\Http\Controllers\Support\SupportAuthController;
use App\Http\Controllers\Support\SupportDownloadController;
use App\Http\Controllers\Support\SupportWebhookController;
use App\Http\Middleware\EnsureSupportAccess;
use App\Http\Middleware\SetSupportLocale;
use App\Livewire\Support\Portal;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/support/{provider}', SupportWebhookController::class)->whereIn('provider', ['github', 'sentry'])->middleware('throttle:120,1')->name('support.webhook');

Route::prefix('/support/{project}')->middleware(SetSupportLocale::class)->name('support.')->group(function (): void {
    Route::get('/login', [SupportAuthController::class, 'login'])->name('login');
    Route::post('/login', [SupportAuthController::class, 'send'])->middleware('throttle:10,1')->name('login.send');
    Route::get('/login/{token}', [SupportAuthController::class, 'loginConfirm'])->where('token', '[A-Za-z0-9]{64}')->name('login.confirm');
    Route::post('/login/{token}', [SupportAuthController::class, 'loginConsume'])->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{64}')->name('login.consume');
    Route::get('/confirm/{token}', [SupportAuthController::class, 'confirm'])->where('token', '[A-Za-z0-9]{64}')->name('confirm');
    Route::post('/confirm/{token}', [SupportAuthController::class, 'consume'])->middleware('throttle:10,1')->where('token', '[A-Za-z0-9]{64}')->name('consume');
    Route::post('/logout', [SupportAuthController::class, 'logout'])->name('logout');
    Route::get('/requests/{reference}/attachments/{media}', [SupportDownloadController::class, 'attachment'])->middleware('signed')->whereUuid('reference')->whereNumber('media')->name('attachment');
    Route::middleware(EnsureSupportAccess::class)->group(function (): void {
        Route::livewire('/', Portal::class)->defaults('section', 'requests')->name('home');
        Route::livewire('/new', Portal::class)->defaults('section', 'create')->name('create');
        Route::livewire('/requests/{reference}', Portal::class)->defaults('section', 'detail')->whereUuid('reference')->name('request');
        Route::livewire('/plan', Portal::class)->defaults('section', 'plan')->name('plan');
        Route::livewire('/invoices', Portal::class)->defaults('section', 'invoices')->name('invoices');
        Route::livewire('/reports', Portal::class)->defaults('section', 'reports')->name('reports');
        Route::get('/invoices/{invoice}/pdf', [SupportDownloadController::class, 'invoice'])->whereNumber('invoice')->name('invoice');
    });
});
Route::middleware(EnsureSupportAccess::class)->prefix('/api/support/v1/projects/{project}')->name('support.api.')->group(function (): void {
    Route::get('/requests', [SupportApiController::class, 'index'])->name('requests');
    Route::get('/requests/{reference}', [SupportApiController::class, 'show'])->whereUuid('reference')->name('request');
});
