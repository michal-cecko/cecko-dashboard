<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachMessage extends Model
{
    protected $table = 'stride_coach_messages';

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'cards',
        'adjustments',
        'input_tokens',
        'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'adjustments' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CoachConversation::class, 'conversation_id');
    }
}
