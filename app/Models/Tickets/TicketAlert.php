<?php

namespace App\Models\Tickets;

use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAlert extends Model
{
    public const EVENT_CREATED = 'created';

    public const EVENT_REPLIED = 'replied';

    public const EVENT_URGENT = 'urgent';

    protected $fillable = [
        'ticket_id',
        'department_id',
        'event',
        'is_urgent',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_urgent' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'department_id');
    }

    /**
     * @param  Builder<TicketAlert>  $query
     */
    public function scopeUnsent(Builder $query): void
    {
        $query->whereNull('sent_at');
    }
}
