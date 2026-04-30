<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum ConcernTriggerEnum: string
{
    use EnumHelper;

    case MILEAGE = 'mileage';
    case TIME = 'time';
    case SYMPTOM = 'symptom';
    case RECALL = 'recall';
    case SEASONAL = 'seasonal';
}
