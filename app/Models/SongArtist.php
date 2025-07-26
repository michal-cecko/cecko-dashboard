<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SongArtist extends Model
{
    protected $fillable = [
        'name',
    ];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_artist_song_pivot', 'artist_id', 'song_id')
            ->withTimestamps();
    }
}
