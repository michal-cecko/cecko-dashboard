<?php

namespace App\Models\Songs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SongTag extends Model
{
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
