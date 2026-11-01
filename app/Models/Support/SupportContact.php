<?php

namespace App\Models\Support;

use Database\Factories\Support\SupportContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class SupportContact extends Authenticatable
{
    /** @use HasFactory<SupportContactFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $attributes = ['is_active' => true];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(SupportProject::class, 'support_contact_project')->withPivot('can_approve')->withTimestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (self $contact): void {
            $contact->email = mb_strtolower(trim($contact->email));
            if ($contact->isDirty('email') && $contact->exists) {
                SupportLoginLink::query()->where('contact_id', $contact->id)->whereNull('used_at')->update(['used_at' => now()]);
                SupportConfirmation::query()->where('contact_id', $contact->id)->whereNull('used_at')->update(['used_at' => now()]);
            }
        });
    }
}
