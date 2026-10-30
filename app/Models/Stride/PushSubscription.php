<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use App\Models\Common\UserApiToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushSubscription extends Model
{
    protected $table = 'stride_push_subscriptions';

    protected $fillable = ['user_id', 'token_id', 'endpoint_hash', 'endpoint', 'public_key', 'auth_token'];

    protected function casts(): array
    {
        return [
            'endpoint' => 'encrypted',
            'public_key' => 'encrypted',
            'auth_token' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(UserApiToken::class, 'token_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PushDelivery::class, 'subscription_id');
    }
}
