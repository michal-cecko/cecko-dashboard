<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Models\Songs\Song;

class SongController extends Controller
{
    public function index()
    {
        $songs = Song::with(['artists', 'tags'])->get();

        return SongResource::collection($songs);
    }
}
