<?php

namespace App\Filament\Common\Resources\Users\Pages;

use App\Filament\Common\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Nový používateľ';
}
