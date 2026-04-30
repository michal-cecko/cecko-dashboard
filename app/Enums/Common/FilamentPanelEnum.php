<?php

namespace App\Enums\Common;

use App\Traits\Common\EnumHelper;

enum FilamentPanelEnum: string
{
    use EnumHelper;

    case SONGS = 'songs';
    case INVOICES = 'invoices';
    case TOOLKIT = 'toolkit';
    case GARAZ = 'garaz';
}
