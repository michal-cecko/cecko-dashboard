<?php

namespace App\Models\Stride;

use App\Models\Common\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A coach plan-edit, as a propose→confirm record. Staged as `status='proposed'`
 * with a machine `payload`; the user confirms and ProposalApplyService applies it
 * (`status='applied'`). The same row is then the change-history entry.
 */
class AiAdjustment extends Model
{
    protected $table = 'stride_ai_adjustments';

    protected $fillable = [
        'user_id',
        'session_id',
        'block_id',
        'conversation_id',
        'scope',
        'status',
        'kind',
        'operation',
        'target',
        'text',
        'why',
        'payload',
        'preview',
        'applied_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'preview' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CoachConversation::class, 'conversation_id');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeProposed(Builder $query): Builder
    {
        return $query->where('status', 'proposed');
    }

    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied');
    }

    public function scopeForBlock(Builder $query, int $blockId): Builder
    {
        return $query->where('block_id', $blockId);
    }

    /**
     * Still-pending proposals that stage the SAME edit as this one (same user,
     * operation, scope, target rows, and rendered text) — the tool loop can stage
     * an edit repeatedly, so resolving one proposal must resolve its twins.
     */
    public function scopePendingDuplicatesOf(Builder $query, self $adjustment): Builder
    {
        return $query->whereKeyNot($adjustment->id)
            ->where('user_id', $adjustment->user_id)
            ->where('status', 'proposed')
            ->where('operation', $adjustment->operation)
            ->where('scope', $adjustment->scope)
            ->where('text', $adjustment->text)
            ->when($adjustment->block_id !== null,
                fn (Builder $q) => $q->where('block_id', $adjustment->block_id),
                fn (Builder $q) => $q->whereNull('block_id'))
            ->when($adjustment->session_id !== null,
                fn (Builder $q) => $q->where('session_id', $adjustment->session_id),
                fn (Builder $q) => $q->whereNull('session_id'));
    }
}
