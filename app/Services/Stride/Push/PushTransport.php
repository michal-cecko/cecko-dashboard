<?php

namespace App\Services\Stride\Push;

use App\Models\Stride\PushSubscription;

interface PushTransport
{
    public function send(PushSubscription $subscription, array $payload): PushSendResult;
}
