<?php

namespace App\Filament\Support\Resources\SupportRequests;

use App\Filament\Support\Resources\SupportRequests\Pages\CreateSupportRequest;
use App\Filament\Support\Resources\SupportRequests\Pages\EditSupportRequest;
use App\Filament\Support\Resources\SupportRequests\Pages\ListSupportRequests;
use App\Filament\Support\Resources\SupportRequests\Pages\ViewSupportRequest;
use App\Filament\Support\Resources\SupportRequests\Schemas\SupportRequestForm;
use App\Filament\Support\Resources\SupportRequests\Schemas\SupportRequestInfolist;
use App\Filament\Support\Resources\SupportRequests\Tables\SupportRequestsTable;
use App\Models\Support\SupportRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportRequestResource extends Resource
{
    protected static ?string $pluralLabel = 'Požiadavky';

    protected static ?string $label = 'Požiadavka';

    protected static ?string $model = SupportRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    public static function form(Schema $schema): Schema
    {
        return SupportRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportRequests::route('/'),
            'create' => CreateSupportRequest::route('/create'),
            'view' => ViewSupportRequest::route('/{record}'),
            'edit' => EditSupportRequest::route('/{record}/edit'),
        ];
    }
}
