<?php

namespace App\Models\Songs;

use Database\Factories\SongGenreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SongGenre extends Model
{
    /** @use HasFactory<SongGenreFactory> */
    use HasFactory;

    protected static function newFactory(): SongGenreFactory
    {
        return SongGenreFactory::new();
    }

    protected $fillable = [
        'name',
        'color',
    ];

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class, 'genre_id');
    }
}
