<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Controller;
use App\Http\Resources\Songs\SongGenreResource;
use App\Models\Songs\SongGenre;

class SongGenreController extends Controller
{
    public function index()
    {
        $songGenres = SongGenre::all();
        return SongGenreResource::collection($songGenres);
    }
}
