<?php

namespace App\Filament\Support\Resources\SupportProjects\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    public function form(Schema $schema): Schema
    {
        return $schema->components([Toggle::make('can_approve')->label('Môže schvaľovať platené kroky')->default(false)]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), IconColumn::make('can_approve')->boolean()])
            ->headerActions([AttachAction::make()->preloadRecordSelect()->schema(fn (AttachAction $action): array => [$action->getRecordSelect(), Toggle::make('can_approve')->label('Môže schvaľovať')->default(false)])])
            ->recordActions([EditAction::make(), DetachAction::make()])->toolbarActions([]);
    }
}
