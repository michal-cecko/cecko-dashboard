<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum CheckOutcomeEnum: string
{
    use EnumHelper;

    case PENDING = 'pending';
    case PASS = 'pass';
    case FAIL = 'fail';
    case UNCERTAIN = 'uncertain';
    case SKIPPED = 'skipped';
}
