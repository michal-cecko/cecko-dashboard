<?php

namespace App\Filament\Support\Resources\SupportReports\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SupportReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->disabled(fn ($record): bool => $record !== null && $record->status !== 'draft')->components([
            Select::make('project_id')->relationship('project', 'name')->required()->searchable(),
            DatePicker::make('month')->required()->rule(static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                if (substr((string) $value, 8, 2) !== '01') {
                    $fail('Zvoľte prvý deň mesiaca.');
                }
            })->unique(table: 'support_reports', column: 'month', ignoreRecord: true, modifyRuleUsing: fn ($rule, Get $get) => $rule->where('project_id', $get('project_id'))),
            Textarea::make('sections.fixes')->label('Opravy')->maxLength(10000),
            Textarea::make('sections.updates')->label('Aktualizácie')->maxLength(10000),
            Textarea::make('sections.features')->label('Úpravy a nové funkcie')->maxLength(10000),
            Textarea::make('sections.search_console')->label('Google Search Console')->maxLength(10000),
            Textarea::make('sections.uptime')->label('Dostupnosť')->maxLength(10000),
            Textarea::make('sections.backups')->label('Zálohy')->maxLength(10000),
        ]);
    }
}
