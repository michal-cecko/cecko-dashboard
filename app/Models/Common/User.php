<?php

namespace App\Models\Common;

use App\Casts\UserCapabilitiesCast;
use App\Enums\Common\FilamentPanelEnum;
use App\Enums\Common\UserCapabilityEnum;
use App\Models\Invoices\Company;
use App\Models\Invoices\Customer;
use App\Models\Stride\PushSubscription;
use App\Models\Toolkit\Gallery;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'customer_id',
        'ticket_notices_enabled',
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
            'ticket_notices_enabled' => 'boolean',
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

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'user_id');
    }

    public function showsAllInvoiceCompanies(): bool
    {
        return session()->get('invoices.show_all_companies', false)
            && $this->hasCapability(UserCapabilityEnum::MANAGE_ALL_INVOICES);
    }

    public function canImpersonate(): bool
    {
        return $this->hasCapability(UserCapabilityEnum::MANAGE_USERS);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match (FilamentPanelEnum::tryFrom($panel->getId())) {
            FilamentPanelEnum::SONGS => $this->hasCapability(UserCapabilityEnum::VIEW_SONGS),
            FilamentPanelEnum::INVOICES => $this->hasCapability(UserCapabilityEnum::VIEW_INVOICES),
            FilamentPanelEnum::TOOLKIT => $this->hasCapability(UserCapabilityEnum::VIEW_MEDIA),
            FilamentPanelEnum::GARAZ => $this->hasCapability(UserCapabilityEnum::VIEW_GARAZ),
            FilamentPanelEnum::TICKETS => $this->hasCapability(UserCapabilityEnum::VIEW_TICKETS)
                || $this->hasCapability(UserCapabilityEnum::MANAGE_TICKETS),
            FilamentPanelEnum::CLIENT => $this->customer_id !== null,
            default => false,
        };
    }

    /**
     * Read by the ticketing plugin through `navigation_visibility.field`: 'admin' sees all tickets and settings.
     */
    protected function ticketingRole(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->hasCapability(UserCapabilityEnum::MANAGE_TICKETS) ? 'admin' : null);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
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
