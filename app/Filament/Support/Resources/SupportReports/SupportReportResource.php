<?php

namespace App\Filament\Support\Resources\SupportReports;

use App\Filament\Support\Resources\SupportReports\Pages\CreateSupportReport;
use App\Filament\Support\Resources\SupportReports\Pages\EditSupportReport;
use App\Filament\Support\Resources\SupportReports\Pages\ListSupportReports;
use App\Filament\Support\Resources\SupportReports\Pages\ViewSupportReport;
use App\Filament\Support\Resources\SupportReports\Schemas\SupportReportForm;
use App\Filament\Support\Resources\SupportReports\Schemas\SupportReportInfolist;
use App\Filament\Support\Resources\SupportReports\Tables\SupportReportsTable;
use App\Models\Support\SupportReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportReportResource extends Resource
{
    protected static ?string $pluralLabel = 'Zhrnutia';

    protected static ?string $label = 'Zhrnutie';

    protected static ?string $model = SupportReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        return SupportReportForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportReportInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportReports::route('/'),
            'create' => CreateSupportReport::route('/create'),
            'view' => ViewSupportReport::route('/{record}'),
            'edit' => EditSupportReport::route('/{record}/edit'),
        ];
    }
}
