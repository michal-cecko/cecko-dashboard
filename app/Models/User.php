<?php

namespace App\Models;

use App\Casts\UserCapabilitiesCast;
use App\Enums\FilamentPanelEnum;
use App\Enums\UserCapabilityEnum;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'capabilities'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'capabilities' => UserCapabilitiesCast::class,
        ];
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return Storage::disk("public")->url($this->avatar_path);
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function hasCapability(UserCapabilityEnum $capability): bool {
        return in_array($capability, $this->capabilities);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match (FilamentPanelEnum::tryFrom($panel->getId())) {
            FilamentPanelEnum::SONGS => $this->hasCapability(UserCapabilityEnum::VIEW_SONGS),
            default => false,
        };
    }
}
