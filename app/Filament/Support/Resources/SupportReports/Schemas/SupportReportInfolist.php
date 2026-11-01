<?php

namespace App\Filament\Support\Resources\SupportReports\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportReportInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([TextEntry::make('month'), TextEntry::make('status'), TextEntry::make('sections.fixes'), TextEntry::make('sections.updates'), TextEntry::make('sections.features'), TextEntry::make('sections.search_console'), TextEntry::make('sections.uptime'), TextEntry::make('sections.backups')]);
    }
}
