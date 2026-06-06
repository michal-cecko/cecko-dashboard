<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InjuryJournalEntry extends Model
{
    protected $table = 'stride_injury_journal_entries';

    protected $fillable = [
        'injury_id',
        'entry_date',
        'trend',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    public function injury(): BelongsTo
    {
        return $this->belongsTo(Injury::class);
    }
}
