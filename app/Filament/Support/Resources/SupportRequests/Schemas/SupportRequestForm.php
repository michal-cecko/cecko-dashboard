<?php

namespace App\Filament\Support\Resources\SupportRequests\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupportRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('project_id')->relationship('project', 'name')->required()->searchable()->disabled(fn ($record): bool => $record !== null),
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('description')->required()->maxLength(10000)->rows(5),
            TextInput::make('url')->url()->maxLength(2048),
            Select::make('type')->options(['bug' => 'Chyba', 'change' => 'Drobná zmena', 'feature' => 'Nová funkcia', 'question' => 'Otázka'])->required()->default('question'),
            Toggle::make('is_critical')->default(false),
            TextInput::make('internal_estimate_minutes')->label('Interný odhad (min, klient ho nevidí)')->integer()->minValue(0),
            Repeater::make('checklist')->label('Verejný zoznam')->maxItems(100)->schema([TextInput::make('label')->required()->maxLength(500), Toggle::make('checked')->default(false)]),
        ]);
    }
}
