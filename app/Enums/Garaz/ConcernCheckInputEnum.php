<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum ConcernCheckInputEnum: string
{
    use EnumHelper;

    case PHOTO = 'photo';
    case VIDEO = 'video';
    case TEXT = 'text';
    case NUMBER = 'number';
    case OBD_CODES = 'obd_codes';
    case AUTO_LOOKUP = 'auto_lookup';
    case CHOICE = 'choice';
    case RATING = 'rating';
}
