<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Models\Song;
use Illuminate\Http\Request;

class SongController extends Controller
{
    public function index()
    {
        $songs = Song::with(['genre', 'artists', 'tags'])->get();

        return SongResource::collection($songs);
    }
}
