<?php

namespace App\Support\Stride;

use App\Models\Common\User;
use App\Models\Stride\StrideProfile;

/**
 * The athlete's UI language ('en' | 'sk'), read from their Stride profile
 * preferences. Used for DISPLAY only — exercise names sent to the AI, stored on
 * sessions, or matched against personal records stay English.
 */
class StrideLanguage
{
    private static ?string $override = null;

    /** @var array<int, string> resolved languages, so a payload of 500 rows costs one query */
    private static array $cache = [];

    /** Resolve for the current request's user (defaults to English). */
    public static function current(): string
    {
        if (self::$override !== null) {
            return self::$override;
        }

        // Stride authenticates with a Bearer UserApiToken and resolves the user
        // onto the request (AuthenticateStrideToken), not through an auth guard.
        $user = request()->user();

        return $user instanceof User ? self::forUser($user) : 'en';
    }

    public static function forUser(User $user): string
    {
        if (isset(self::$cache[$user->id])) {
            return self::$cache[$user->id];
        }

        $profile = StrideProfile::where('user_id', $user->id)->first();
        $lang = strtolower((string) (($profile?->preferences['language'] ?? null) ?: 'en'));

        return self::$cache[$user->id] = $lang === 'sk' ? 'sk' : 'en';
    }

    /** Testing / queue hook: pin the language for the current process. */
    public static function fake(?string $lang): void
    {
        self::$override = $lang;
        self::$cache = [];
    }
}
