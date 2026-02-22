<?php

namespace App\Models\Songs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongGenre extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'genre_id');
    }
}
