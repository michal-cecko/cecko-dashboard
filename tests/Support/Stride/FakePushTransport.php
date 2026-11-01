<?php

namespace Tests\Support\Stride;

use App\Models\Stride\PushSubscription;
use App\Services\Stride\Push\PushSendResult;
use App\Services\Stride\Push\PushTransport;

class FakePushTransport implements PushTransport
{
    public array $results = [];

    public array $sent = [];

    public function send(PushSubscription $subscription, array $payload): PushSendResult
    {
        $this->sent[] = $payload;

        return array_shift($this->results) ?? PushSendResult::sent();
    }
}
