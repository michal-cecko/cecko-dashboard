<?php

namespace App\Filament\Support\Resources\SupportProjects\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([TextEntry::make('name'), TextEntry::make('slug'), TextEntry::make('subscription.plan'), TextEntry::make('subscription.paid_period_end'), TextEntry::make('subscription.hosting_period_start')->label('Začiatok zaplateného hostingu')->date(), TextEntry::make('subscription.hosting_period_end')->label('Obnova hostingu')->date(), TextEntry::make('subscription.hosting_price_minor')->label('Zaplatená cena hostingu (centy)')]);
    }
}
