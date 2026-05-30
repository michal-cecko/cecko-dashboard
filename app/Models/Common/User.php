<?php

namespace App\Models\Common;

use App\Casts\UserCapabilitiesCast;
use App\Enums\Common\FilamentPanelEnum;
use App\Enums\Common\UserCapabilityEnum;
use App\Models\Invoices\Company;
use App\Models\Toolkit\Gallery;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasMedia, HasName, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithMedia, Notifiable, PasskeyAuthenticatable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_path',
        'capabilities',
        'active_company_id',
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

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('avatar');

        if (! $url) {
            return 'https://ui-avatars.com/api/?name='.urlencode($this->getFilamentName()).'&color=7c3aed&background=f3f4f6';
        }

        return $url;
    }

    public function getFilamentName(): string
    {
        return $this->name;
    }

    public function hasCapability(UserCapabilityEnum $capability): bool
    {
        return in_array($capability, $this->capabilities);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match (FilamentPanelEnum::tryFrom($panel->getId())) {
            FilamentPanelEnum::SONGS => $this->hasCapability(UserCapabilityEnum::VIEW_SONGS),
            FilamentPanelEnum::INVOICES => $this->hasCapability(UserCapabilityEnum::VIEW_INVOICES),
            FilamentPanelEnum::TOOLKIT => $this->hasCapability(UserCapabilityEnum::VIEW_MEDIA),
            FilamentPanelEnum::GARAZ => $this->hasCapability(UserCapabilityEnum::VIEW_GARAZ),
            default => false,
        };
    }

    public function activeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'active_company_id');
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function sharedGalleries(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class, 'gallery_user')
            ->withPivot('permission')
            ->withTimestamps();
    }
}
