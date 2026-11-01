<?php

namespace App\Filament\Support\Resources\SupportContacts;

use App\Filament\Support\Resources\SupportContacts\Pages\CreateSupportContact;
use App\Filament\Support\Resources\SupportContacts\Pages\EditSupportContact;
use App\Filament\Support\Resources\SupportContacts\Pages\ListSupportContacts;
use App\Filament\Support\Resources\SupportContacts\Pages\ViewSupportContact;
use App\Filament\Support\Resources\SupportContacts\Schemas\SupportContactForm;
use App\Filament\Support\Resources\SupportContacts\Schemas\SupportContactInfolist;
use App\Filament\Support\Resources\SupportContacts\Tables\SupportContactsTable;
use App\Models\Support\SupportContact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportContactResource extends Resource
{
    protected static ?string $pluralLabel = 'Kontakty';

    protected static ?string $label = 'Kontakt';

    protected static ?string $model = SupportContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return SupportContactForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportContactInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportContactsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportContacts::route('/'),
            'create' => CreateSupportContact::route('/create'),
            'view' => ViewSupportContact::route('/{record}'),
            'edit' => EditSupportContact::route('/{record}/edit'),
        ];
    }
}
