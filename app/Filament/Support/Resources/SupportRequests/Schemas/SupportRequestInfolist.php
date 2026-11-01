<?php

namespace App\Filament\Support\Resources\SupportRequests\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([TextEntry::make('reference'), TextEntry::make('title'), TextEntry::make('description'), TextEntry::make('status'), TextEntry::make('internal_estimate_minutes'), RepeatableEntry::make('events')->schema([TextEntry::make('body'), TextEntry::make('occurred_at')->dateTime()]), RepeatableEntry::make('internalNotes')->label('Interné poznámky')->schema([TextEntry::make('body')])]);
    }
}
