<?php

namespace App\Services\Stride\Push;

final readonly class PushSendResult
{
    public function __construct(public string $status) {}

    public static function sent(): self
    {
        return new self('sent');
    }

    public static function expired(): self
    {
        return new self('expired');
    }

    public static function temporaryFailure(): self
    {
        return new self('temporary');
    }
}
