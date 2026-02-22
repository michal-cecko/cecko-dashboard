<?php

namespace App\Http\Resources\Songs;

use App\Services\Songs\ColorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SongTagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => ColorService::translateStringColorToHex($this->color),
        ];
    }
}
