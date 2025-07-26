<?php

namespace App\Casts;

use App\Enums\UserCapabilityEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class UserCapabilitiesCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        return array_map(
            fn($item) => UserCapabilityEnum::tryFrom($item),
            $decoded
        );
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return json_encode([]);
        }

        if (!is_array($value)) {
            return json_encode([]);
        }

        $enumValues = array_map(
            fn($item) => $item instanceof UserCapabilityEnum ? $item->value : $item,
            $value
        );

        return json_encode($enumValues);
    }
}
