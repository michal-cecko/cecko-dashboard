<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum MotorcycleEngineLayoutEnum: string
{
    use EnumHelper;

    case SINGLE = 'single';
    case PARALLEL_TWIN = 'parallel_twin';
    case V_TWIN = 'v_twin';
    case INLINE_3 = 'inline_3';
    case INLINE_4 = 'inline_4';
    case BOXER = 'boxer';
    case OTHER = 'other';
}
