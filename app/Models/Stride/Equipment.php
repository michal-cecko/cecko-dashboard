<?php

namespace App\Models\Stride;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'stride_equipment';

    protected $fillable = [
        'key',
        'name',
        'group',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }
}
