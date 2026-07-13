<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoachConversation extends Model
{
    protected $table = 'stride_coach_conversations';

    protected $fillable = [
        'user_id',
        'block_id',
        'title',
        'persona_key',
        'summary',
        'summarized_through_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'summarized_through_id' => 'integer',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CoachMessage::class, 'conversation_id')->orderBy('id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForBlock(Builder $query, ?int $blockId): Builder
    {
        return $query->where('block_id', $blockId);
    }
}
