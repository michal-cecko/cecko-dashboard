<?php

namespace App\Filament\Songs\Resources\SongGenres;

use App\Filament\Songs\Resources\SongGenres\Pages\CreateSongGenre;
use App\Filament\Songs\Resources\SongGenres\Pages\EditSongGenre;
use App\Filament\Songs\Resources\SongGenres\Pages\ListSongGenres;
use App\Filament\Songs\Resources\SongGenres\Schemas\SongGenreForm;
use App\Filament\Songs\Resources\SongGenres\Tables\SongGenresTable;
use App\Models\SongGenre;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SongGenreResource extends Resource
{
    protected static ?string $model = SongGenre::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|null|UnitEnum $navigationGroup = 'Piesne';

    protected static ?int $navigationSort = 3;

    protected static ?string $label = 'Žáner';
    protected static ?string $pluralLabel = 'Žánre';

    public static function form(Schema $schema): Schema
    {
        return SongGenreForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SongGenresTable::configure($table);
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
            'index' => ListSongGenres::route('/'),
            'create' => CreateSongGenre::route('/create'),
            'edit' => EditSongGenre::route('/{record}/edit'),
        ];
    }
}
