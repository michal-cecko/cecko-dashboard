<?php

namespace App\Filament\Songs\Resources\SongTags;

use App\Filament\Songs\Resources\SongTags\Pages\CreateSongTag;
use App\Filament\Songs\Resources\SongTags\Pages\EditSongTag;
use App\Filament\Songs\Resources\SongTags\Pages\ListSongTags;
use App\Filament\Songs\Resources\SongTags\Schemas\SongTagForm;
use App\Filament\Songs\Resources\SongTags\Tables\SongTagsTable;
use App\Models\SongTag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SongTagResource extends Resource
{
    protected static ?string $model = SongTag::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|null|UnitEnum $navigationGroup = 'Piesne';

    protected static ?string $label = 'Značka';
    protected static ?string $pluralLabel = 'Značky';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return SongTagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SongTagsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSongTags::route('/'),
            'create' => CreateSongTag::route('/create'),
            'edit' => EditSongTag::route('/{record}/edit'),
        ];
    }
}
