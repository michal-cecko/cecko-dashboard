<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum FilamentPanelEnum: string
{
    use EnumHelper;

    case SONGS = 'songs';
    case INVOICES = 'invoices';
}
