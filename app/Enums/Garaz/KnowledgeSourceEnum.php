<?php

namespace App\Enums\Garaz;

use App\Traits\Common\EnumHelper;

enum KnowledgeSourceEnum: string
{
    use EnumHelper;

    case MANUAL = 'manual';
    case BOOKMARKLET = 'bookmarklet';
    case EMAIL = 'email';
    case FORUM = 'forum';
    case AI = 'ai';
}
