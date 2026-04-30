<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum FrameMaterialEnum: string
{
    use EnumHelper;

    case STEEL = 'steel';
    case ALUMINUM = 'aluminum';
    case CARBON = 'carbon';
    case TITANIUM = 'titanium';
}
