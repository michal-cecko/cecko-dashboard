<?php

namespace App\Filament\Songs\Resources\SongArtists;

use App\Filament\Songs\Resources\SongArtists\Pages\CreateSongArtist;
use App\Filament\Songs\Resources\SongArtists\Pages\EditSongArtist;
use App\Filament\Songs\Resources\SongArtists\Pages\ListSongArtists;
use App\Filament\Songs\Resources\SongArtists\Pages\ViewSongArtist;
use App\Filament\Songs\Resources\SongArtists\Schemas\SongArtistForm;
use App\Filament\Songs\Resources\SongArtists\Tables\SongArtistsTable;
use App\Models\SongArtist;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SongArtistResource extends Resource
{
    protected static ?string $model = SongArtist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|null|UnitEnum $navigationGroup = 'Piesne';

    protected static ?int $navigationSort = 2;
    protected static ?string $label = 'Autor';
    protected static ?string $pluralLabel = 'Autori';

    public static function form(Schema $schema): Schema
    {
        return SongArtistForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SongArtistsTable::configure($table);
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
            'index' => ListSongArtists::route('/'),
            'create' => CreateSongArtist::route('/create'),
            'view' => ViewSongArtist::route('/{record}'),
            'edit' => EditSongArtist::route('/{record}/edit'),
        ];
    }
}
