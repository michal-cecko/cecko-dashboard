<?php

namespace App\Models\Songs;

use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory;

    protected static function newFactory(): SongFactory
    {
        return SongFactory::new();
    }

    protected $fillable = [
        'title',
        'number',
        'lyrics',
        'genre_id',
        'bpm',
    ];

    public function genre(): BelongsTo
    {
        return $this->belongsTo(SongGenre::class, 'genre_id');
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(SongArtist::class, 'song_artist_song_pivot', 'song_id', 'artist_id')
            ->withTimestamps();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(SongTag::class, 'song_tag_song_pivot', 'song_id', 'tag_id')
            ->withTimestamps();
    }
}
