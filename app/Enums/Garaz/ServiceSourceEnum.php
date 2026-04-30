<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum ServiceSourceEnum: string
{
    use EnumHelper;

    case SHOP = 'shop';
    case DIY = 'diy';
    case IMPORTED = 'imported';
    case ASSESSMENT = 'assessment';
}
