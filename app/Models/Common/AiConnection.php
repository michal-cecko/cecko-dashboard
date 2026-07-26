<?php

namespace App\Models\Common;

use App\Services\Common\Ai\AiCredentials;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's chosen AI provider + model and their encrypted credentials (BYOK).
 * Shared app-wide (Stride coach, Garáž, …). No row / provider "free" means the
 * user runs on the app-subsidised free tier.
 *
 * @property string $provider anthropic|gemini|openai|free
 * @property string $auth_type api_key|none
 * @property array|null $credentials {api_key}
 * @property string|null $model
 * @property string $status unverified|active|invalid
 */
class AiConnection extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'auth_type',
        'credentials',
        'model',
        'status',
        'last_verified_at',
        'last_error',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when the user has connected a working provider of their own. */
    public function isByok(): bool
    {
        return $this->provider !== 'free' && $this->auth_type !== 'none';
    }

    /** Build the driver credentials from the stored API key. */
    public function toCredentials(): AiCredentials
    {
        return new AiCredentials(apiKey: ($this->credentials ?? [])['api_key'] ?? null);
    }

    /** Safe representation for the client — never includes the secret. */
    public function toPublicArray(): array
    {
        return [
            'provider' => $this->provider,
            'auth_type' => $this->auth_type,
            'model' => $this->model,
            'status' => $this->status,
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
        ];
    }
}
