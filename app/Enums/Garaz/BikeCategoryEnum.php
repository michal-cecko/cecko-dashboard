<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum BikeCategoryEnum: string
{
    use EnumHelper;

    case ROAD = 'road';
    case GRAVEL = 'gravel';
    case MTB_HARDTAIL = 'mtb_hardtail';
    case MTB_FULL = 'mtb_full';
    case TREKKING = 'trekking';
    case CITY = 'city';
    case KIDS = 'kids';
    case CARGO = 'cargo';
}
