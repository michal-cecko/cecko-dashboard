<?php

use App\Enums\UserCapabilityEnum;

return [
    UserCapabilityEnum::class => [
        UserCapabilityEnum::VIEW_SONGS->value => 'Prístup ku knihe piesní',
        UserCapabilityEnum::MANAGE_SONGS->value => 'Upravovať knihu piesní',
        UserCapabilityEnum::VIEW_MOBILE_APPS->value => 'Prístup k aplikáciam',
        UserCapabilityEnum::MANAGE_MOBILE_APPS->value => 'Upravovať aplikácie',
        UserCapabilityEnum::MANAGE_USERS->value => 'Upravovať používateľov',
    ]
];
