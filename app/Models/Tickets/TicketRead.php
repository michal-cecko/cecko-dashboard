<?php

namespace App\Models\Tickets;

use App\Models\Common\User;
use daacreators\CreatorsTicketing\Models\Ticket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRead extends Model
{
    protected $table = 'ticket_user_reads';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'read_at',
        'notified_at',
        'notice_pending_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'notified_at' => 'datetime',
            'notice_pending_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
