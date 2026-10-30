<?php

namespace App\Services\Stride\Push;

use App\Models\Stride\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class WebPushTransport implements PushTransport
{
    public function __construct(private readonly ?HttpClientInterface $httpClient = null) {}

    public function send(PushSubscription $subscription, array $payload): PushSendResult
    {
        $publicKey = config('stride-push.public_key');
        $privateKey = config('stride-push.private_key');
        $subject = config('stride-push.subject');

        if (! is_string($publicKey) || ! is_string($privateKey) || ! is_string($subject)
            || $publicKey === '' || $privateKey === '' || $subject === '') {
            return PushSendResult::temporaryFailure();
        }

        try {
            $client = new Psr18Client($this->httpClient ?? HttpClient::create(['max_redirects' => 0, 'timeout' => 10]));
            $push = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ], [
                'TTL' => 0,
                'urgency' => 'normal',
            ], $client, $client, $client);

            $report = $push->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->public_key,
                        'auth' => $subscription->auth_token,
                    ],
                ]),
                json_encode($payload, JSON_THROW_ON_ERROR),
            );

            if ($report->isSuccess()) {
                return PushSendResult::sent();
            }

            return $report->isSubscriptionExpired()
                ? PushSendResult::expired()
                : PushSendResult::temporaryFailure();
        } catch (Throwable) {
            return PushSendResult::temporaryFailure();
        }
    }
}
