<?php

namespace App\Enums\Tickets;

use App\Traits\Common\EnumHelper;

enum PlanRequestStatusEnum: string
{
    use EnumHelper;

    /** An upgrade that waits for staff. */
    case PENDING = 'pending';

    /** A downgrade that waits for the end of the paid period. */
    case SCHEDULED = 'scheduled';

    case DONE = 'done';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::PENDING, self::SCHEDULED];
    }
}
