<?php

namespace App\Services\Common\Ai;

use App\Models\Common\AiConnection;
use App\Models\Common\User;

/**
 * Read-only view of the provider + model catalog (config/stride.php → ai) plus
 * the current user's connection. Shared by the AI connection API and /auth/me so
 * the wizard and the in-chat model switcher render from one source.
 */
class AiCatalog
{
    /** The selectable providers (incl. the built-in free tier) and whether OAuth is on. */
    public static function catalog(): array
    {
        $providers = [];

        foreach ((array) config('stride.ai.providers', []) as $key => $cfg) {
            $providers[] = [
                'key' => $key,
                'label' => $cfg['label'] ?? ucfirst($key),
                'auth_types' => array_values(array_filter(
                    $cfg['auth_types'] ?? ['api_key'],
                    fn (string $type) => $type !== 'oauth' || self::oauthEnabled($key),
                )),
                'models' => array_values($cfg['models'] ?? []),
                'default' => $cfg['default'] ?? null,
            ];
        }

        $providers[] = [
            'key' => 'free',
            'label' => (string) config('stride.ai.free.label', 'Free (built-in)'),
            'auth_types' => ['none'],
            'models' => [],
            'default' => null,
        ];

        return [
            'providers' => $providers,
            'oauth_enabled' => self::oauthEnabled('anthropic'),
        ];
    }

    /** The user's connection (safe fields) + the catalog, for the app to render. */
    public static function forUser(User $user): array
    {
        $connection = AiConnection::firstWhere('user_id', $user->id);

        return [
            'connection' => $connection?->toPublicArray() ?? [
                'provider' => 'free',
                'auth_type' => 'none',
                'model' => null,
                'status' => 'active',
                'last_verified_at' => null,
            ],
            'catalog' => self::catalog(),
        ];
    }

    /** Whether a model id is one this provider offers (guards user-supplied ids). */
    public static function isValidModel(string $provider, string $model): bool
    {
        foreach ((array) config("stride.ai.providers.{$provider}.models", []) as $entry) {
            if (($entry['id'] ?? null) === $model) {
                return true;
            }
        }

        return false;
    }

    public static function supportsAuthType(string $provider, string $authType): bool
    {
        if ($authType === 'oauth' && ! self::oauthEnabled($provider)) {
            return false;
        }

        return in_array($authType, (array) config("stride.ai.providers.{$provider}.auth_types", []), true);
    }

    private static function oauthEnabled(string $provider): bool
    {
        return (bool) config('stride.ai.allow_subscription_oauth')
            && ! empty(config("stride.ai.oauth.{$provider}.client_id"));
    }
}
