<?php

namespace App\Enums\Common;

use App\Traits\Common\EnumHelper;

enum UserCapabilityEnum: string
{
    use EnumHelper;
    case VIEW_SONGS = 'VIEW_SONGS';
    case MANAGE_SONGS = 'MANAGE_SONGS';
    case MANAGE_USERS = 'MANAGE_USERS';
    case VIEW_MOBILE_APPS = 'VIEW_APPS';
    case MANAGE_MOBILE_APPS = 'MANAGE_APPS';
    case VIEW_INVOICES = 'VIEW_INVOICES';
    case MANAGE_INVOICES = 'MANAGE_INVOICES';
    case VIEW_ALL_INVOICES = 'VIEW_ALL_INVOICES';
    case VIEW_MEDIA = 'VIEW_MEDIA';
    case MANAGE_MEDIA = 'MANAGE_MEDIA';
    case VIEW_ALL_MEDIA = 'VIEW_ALL_MEDIA';
    case VIEW_GARAZ = 'VIEW_GARAZ';
    case MANAGE_GARAZ = 'MANAGE_GARAZ';
}
