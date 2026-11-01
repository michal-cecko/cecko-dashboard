<?php

namespace App\Enums\Support;

enum SupportPlanEnum: string
{
    case None = 'none';
    case Start = 'start';
    case Standard = 'standard';
    case Pro = 'pro';

    public function label(): string
    {
        return $this === self::None ? 'Bez balíka' : $this->name;
    }

    public function minutes(): int
    {
        return match ($this) {
            self::None => 0, self::Start => 90, self::Standard => 180, self::Pro => 300,
        };
    }

    public function price(string $currency = 'EUR', bool $yearly = false): int
    {
        $monthly = match ($this) {
            self::None => 0, self::Start => 19000, self::Standard => 39000, self::Pro => 79000,
        };

        return $monthly * ($yearly ? 10 : 1) * ($currency === 'CZK' ? 25 : 1);
    }

    public function credit(string $currency = 'EUR'): int
    {
        return $this === self::Pro ? 20000 * ($currency === 'CZK' ? 25 : 1) : 0;
    }

    public function discount(): int
    {
        return match ($this) {
            self::Standard => 5, self::Pro => 10, default => 0
        };
    }

    public function urgentRate(string $currency = 'EUR'): int
    {
        return match ($this) {
            self::None => 12000, self::Start => 10000, self::Standard => 8000, self::Pro => 6000,
        } * ($currency === 'CZK' ? 25 : 1);
    }

    public function hosting(string $currency = 'EUR'): int
    {
        return match ($this) {
            self::None => 15000, self::Start => 11000, self::Standard => 7500, self::Pro => 0,
        } * ($currency === 'CZK' ? 25 : 1);
    }

    public function callMinutes(): int
    {
        return match ($this) {
            self::Standard => 30, self::Pro => 60, default => 0
        };
    }

    public function callRate(string $currency = 'EUR'): int
    {
        return match ($this) {
            self::None => 3000, self::Start => 2500, default => 2000
        } * ($currency === 'CZK' ? 25 : 1);
    }

    public function digestDays(): array
    {
        return match ($this) {
            self::None => [1], self::Start => [1, 4], default => [1, 2, 3, 4, 5]
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $plan): array => [$plan->value => $plan->label()])->all();
    }
}
