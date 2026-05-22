<?php

namespace App\Filament\Toolkit\Resources\FileShares;

use App\Filament\Toolkit\Resources\FileShares\Pages\CreateFileShare;
use App\Filament\Toolkit\Resources\FileShares\Pages\EditFileShare;
use App\Filament\Toolkit\Resources\FileShares\Pages\ListFileShares;
use App\Filament\Toolkit\Resources\FileShares\Pages\ViewFileShare;
use App\Filament\Toolkit\Resources\FileShares\Schemas\FileShareForm;
use App\Filament\Toolkit\Resources\FileShares\Tables\FileSharesTable;
use App\Models\Toolkit\FileShare;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FileShareResource extends Resource
{
    protected static ?string $model = FileShare::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static string|null|UnitEnum $navigationGroup = 'Médiá';

    protected static ?string $label = 'Zdieľaný súbor';

    protected static ?string $pluralLabel = 'Zdieľané súbory';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return FileShareForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FileSharesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFileShares::route('/'),
            'create' => CreateFileShare::route('/create'),
            'view' => ViewFileShare::route('/{record}'),
            'edit' => EditFileShare::route('/{record}/edit'),
        ];
    }
}
