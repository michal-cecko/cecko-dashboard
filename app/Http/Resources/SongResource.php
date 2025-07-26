<?php

namespace App\Http\Resources;

use App\Http\Resources\Songs\SongArtistResource;
use App\Http\Resources\Songs\SongGenreResource;
use App\Http\Resources\Songs\SongTagResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'lyrics' => $this->lyrics,
            'genre' => SongGenreResource::make($this->genre),
            'artists' => SongArtistResource::collection($this->artists),
            'tags' => SongTagResource::collection($this->tags)
        ];
    }
}
