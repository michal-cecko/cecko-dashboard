<?php

namespace App\Enums;

use App\Traits\EnumHelper;

enum UserCapabilityEnum: string
{
    use EnumHelper;
    case VIEW_SONGS = "VIEW_SONGS";
    case MANAGE_SONGS = "MANAGE_SONGS";
    case MANAGE_USERS = "MANAGE_USERS";
    case VIEW_MOBILE_APPS = "VIEW_APPS";
    case MANAGE_MOBILE_APPS = "MANAGE_APPS";
}
