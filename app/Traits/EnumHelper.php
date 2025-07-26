<?php

namespace App\Traits;

trait EnumHelper
{
    public function translation(): string
    {
        return __("enums." . static::class . ".{$this->value}");
    }

    public static function translations(): array
    {
        $arr = [];

        foreach (static::cases() as $case) {
            $arr[$case->value] = $case->translation();
        }

        return $arr;
    }
}
