<?php

namespace App\Services\Stride\Push;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Stride\PushDelivery;
use App\Models\Stride\Session;
use App\Models\Stride\StrideProfile;
use App\Models\Stride\WeightEntry;
use App\Services\Stride\Coach\PokeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class PushScheduler
{
    public function __construct(
        private readonly PushTransport $transport,
        private readonly PokeService $pokes,
    ) {}

    public function sendDue(): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $sent = 0;
        StrideProfile::query()->with(['user', 'user.pushSubscriptions.token'])
            ->whereNotNull('preferences')
            ->chunkById(100, function ($profiles) use (&$sent): void {
                foreach ($profiles as $profile) {
                    $user = $profile->user;
                    if ($user === null || ! $user->hasCapability(UserCapabilityEnum::STRIDE_USER)) {
                        continue;
                    }

                    $preferences = $profile->preferences ?? [];
                    $timezone = $preferences['notification_timezone'] ?? 'Europe/Prague';
                    if (! in_array($timezone, timezone_identifiers_list(), true)) {
                        $timezone = 'Europe/Prague';
                    }
                    $now = CarbonImmutable::now($timezone);
                    $date = $now->toDateString();
                    $isSlovak = ($preferences['language'] ?? 'en') === 'sk';
                    $subscriptions = $user->pushSubscriptions->filter(fn ($subscription): bool => $subscription->token?->isActive() === true
                        && $subscription->token->hasAbility('stride')
                    );
                    if ($subscriptions->isEmpty()) {
                        continue;
                    }

                    $workoutDueAt = $this->dueAt($now, $preferences['workout_reminder_time'] ?? '18:00');
                    if (($preferences['workout_reminder'] ?? false) && $workoutDueAt !== null) {
                        $session = Session::query()->where('user_id', $user->id)
                            ->whereDate('scheduled_date', $date)
                            ->whereHas('block', fn ($query) => $query->where('status', 'active'))
                            ->whereIn('status', ['today', 'planned'])
                            ->where('kind', '!=', 'Rest')
                            ->orderBy('id')
                            ->first();

                        if ($session !== null) {
                            $sent += $this->deliver($subscriptions, 'workout', $date, 'daily', $workoutDueAt, [
                                'title' => $isSlovak ? 'Pripomienka tréningu' : 'Training reminder',
                                'body' => $isSlovak
                                    ? 'Čaká ťa tréning: '.$session->title.'.'
                                    : 'Your '.$session->title.' session is ready when you are.',
                                'tag' => 'stride-workout-'.$date,
                                'url' => '/?screen=home',
                            ]);
                        }
                    }

                    $weighDueAt = $this->dueAt($now, $preferences['weigh_reminder_time'] ?? '06:00');
                    if (($preferences['weigh_reminder'] ?? false) && $weighDueAt !== null
                        && ! WeightEntry::query()->where('user_id', $user->id)->whereDate('recorded_on', $date)->exists()) {
                        $sent += $this->deliver($subscriptions, 'weigh', $date, 'daily', $weighDueAt, [
                            'title' => $isSlovak ? 'Čas na váženie' : 'Time to weigh in',
                            'body' => $isSlovak ? 'Zaznamenaj dnešnú hmotnosť, keď sa ti to hodí.' : 'Log today’s weight when it suits you.',
                            'tag' => 'stride-weigh-'.$date,
                            'url' => '/?screen=weight',
                        ]);
                    }

                    if (($preferences['coach_pokes'] ?? false) && $now->hour >= 7 && $now->hour <= 21) {
                        foreach ($this->pokes->pokes($user, null, null, $now) as $poke) {
                            $pokeDueAt = $this->dueAt($now, $poke['at']);
                            if ($pokeDueAt === null) {
                                continue;
                            }

                            $sent += $this->deliver($subscriptions, 'coach', $date, $poke['slot'], $pokeDueAt, [
                                'title' => $poke['title'],
                                'body' => $poke['body'],
                                'tag' => 'stride-coach-'.$date.'-'.$poke['slot'],
                                'url' => '/?screen=chat',
                            ]);
                        }
                    }
                }
            });

        return $sent;
    }

    private function deliver(Collection $subscriptions, string $type, string $date, string $slot, CarbonImmutable $dueAt, array $payload): int
    {
        $sent = 0;
        $cutoff = $dueAt->addMinutes(15);
        $now = CarbonImmutable::now();
        foreach ($subscriptions as $subscription) {
            if ($subscription->created_at->greaterThan($dueAt)) {
                continue;
            }

            $delivery = PushDelivery::query()
                ->where('subscription_id', $subscription->id)
                ->where('type', $type)
                ->whereDate('local_date', $date)
                ->where('slot', $slot)
                ->first();
            if ($delivery?->status === 'sent' || $now->greaterThanOrEqualTo($cutoff)) {
                continue;
            }
            if ($delivery !== null && $delivery->next_attempt_at !== null && $now->lessThan($delivery->next_attempt_at)) {
                continue;
            }

            $delivery ??= PushDelivery::query()->create([
                'subscription_id' => $subscription->id,
                'type' => $type,
                'local_date' => $date,
                'slot' => $slot,
            ]);

            $result = $this->transport->send($subscription, $payload);
            if ($result->status === 'expired') {
                $subscription->delete();

                continue;
            }
            if ($result->status !== 'sent') {
                $attempts = $delivery->attempts + 1;
                $retryAt = $now->addMinutes(5);
                $delivery->update([
                    'attempts' => $attempts,
                    'status' => $retryAt->greaterThanOrEqualTo($cutoff) ? 'failed' : 'pending',
                    'next_attempt_at' => $retryAt,
                ]);

                continue;
            }

            $delivery->update(['status' => 'sent', 'attempts' => $delivery->attempts + 1, 'delivered_at' => $now]);
            $sent++;
        }

        return $sent;
    }

    private function dueAt(CarbonImmutable $now, string $time): ?CarbonImmutable
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            return null;
        }

        $due = CarbonImmutable::createFromFormat('Y-m-d H:i', $now->toDateString().' '.$time, $now->timezone);

        return $now->greaterThanOrEqualTo($due) && $now->lessThan($due->addMinutes(15)) ? $due : null;
    }

    private function configured(): bool
    {
        return filled(config('stride-push.public_key'))
            && filled(config('stride-push.private_key'))
            && filled(config('stride-push.subject'));
    }
}
