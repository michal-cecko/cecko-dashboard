<?php

namespace App\Filament\Support\Resources\SupportProjects\Schemas;

use App\Enums\Common\UserCapabilityEnum;
use App\Models\Invoices\Company;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupportProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->alphaDash()->regex('/^[a-z0-9-]+$/')->unique(ignoreRecord: true),
            Select::make('company_id')->options(fn (): array => Company::query()->when(! auth()->user()->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES), fn ($query) => $query->where('user_id', auth()->id()))->pluck('name', 'id')->all())->required()->searchable(),
            Select::make('customer_id')->relationship('customer', 'name')->required()->searchable(),
            Select::make('locale')->options(['sk' => 'Slovenčina', 'cs' => 'Čeština'])->default('sk')->required(),
            Select::make('currency')->options(['EUR' => 'EUR', 'CZK' => 'CZK'])->default('EUR')->required()->disabled(fn ($record): bool => $record !== null && ($record->subscription()->exists() || $record->orders()->exists() || $record->recurring_invoice_id !== null)),
            TextInput::make('github_repo')->regex('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/')->maxLength(255),
            TextInput::make('github_installation_id')->integer()->minValue(1),
            TextInput::make('sentry_project_slug')->maxLength(255)->unique(ignoreRecord: true),
            DatePicker::make('warranty_until'),
            Toggle::make('is_active')->default(true),
        ]);
    }
}
