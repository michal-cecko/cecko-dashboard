<?php

namespace App\Services\Tickets;

use App\Enums\Common\FilamentPanelEnum;
use App\Models\Common\User;
use App\Notifications\Tickets\ClientInviteNotification;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Password;

class ClientInvites
{
    public static function send(User $user): void
    {
        $token = Password::broker('clients')->createToken($user);
        $url = Filament::getPanel(FilamentPanelEnum::CLIENT->value)->getResetPasswordUrl($token, $user);

        $user->notify(new ClientInviteNotification($url));
    }
}
