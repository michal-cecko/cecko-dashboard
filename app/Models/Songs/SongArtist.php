<?php

namespace App\Models\Songs;

use Database\Factories\SongArtistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SongArtist extends Model
{
    /** @use HasFactory<SongArtistFactory> */
    use HasFactory;

    protected static function newFactory(): SongArtistFactory
    {
        return SongArtistFactory::new();
    }

    protected $fillable = [
        'name',
    ];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_artist_song_pivot', 'artist_id', 'song_id')
            ->withTimestamps();
    }
}
