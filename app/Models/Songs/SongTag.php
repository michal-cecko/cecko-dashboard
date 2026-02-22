<?php

namespace App\Models\Songs;

use Database\Factories\SongTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SongTag extends Model
{
    /** @use HasFactory<SongTagFactory> */
    use HasFactory;

    protected static function newFactory(): SongTagFactory
    {
        return SongTagFactory::new();
    }

    protected $fillable = [
        'name',
        'color',
    ];

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class, 'song_tag_song_pivot', 'tag_id', 'song_id')
            ->withTimestamps();
    }
}
