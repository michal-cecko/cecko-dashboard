<?php

namespace App\Filament\Support\Resources\SupportContacts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportContactInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([TextEntry::make('name'), TextEntry::make('email'), TextEntry::make('is_active')]);
    }
}
