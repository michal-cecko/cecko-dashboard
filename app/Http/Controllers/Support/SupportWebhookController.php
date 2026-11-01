<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Services\Support\SupportWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SupportWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, SupportWebhookService $service): Response
    {
        $service->receive($request, $provider);

        return response('', 202);
    }
}
