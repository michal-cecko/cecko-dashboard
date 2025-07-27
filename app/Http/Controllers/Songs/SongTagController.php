<?php

namespace App\Http\Controllers\Songs;

use App\Http\Controllers\Controller;
use App\Http\Resources\SongResource;
use App\Http\Resources\Songs\SongTagResource;
use App\Models\Song;
use App\Models\SongTag;

class SongTagController extends Controller
{
    public function index()
    {
        $songTags = SongTag::all();
        return SongTagResource::collection($songTags);
    }
}
