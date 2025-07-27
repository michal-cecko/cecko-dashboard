<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Controller;
use App\Http\Resources\Songs\SongGenreResource;
use App\Http\Resources\Songs\SongTagResource;
use App\Models\SongGenre;
use App\Models\SongTag;

class SongGenreController extends Controller
{
    public function index()
    {
        $songGenres = SongGenre::all();
        return SongGenreResource::collection($songGenres);
    }
}
