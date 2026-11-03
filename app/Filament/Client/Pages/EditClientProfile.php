<?php

namespace App\Filament\Client\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class EditClientProfile extends EditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                Toggle::make('ticket_notices_enabled')
                    ->label('Upozornenia e-mailom')
                    ->helperText('Krátky e-mail s odkazom, keď odpovieme alebo zmeníme stav tiketu. Text tiketu v ňom nie je.'),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }
}
