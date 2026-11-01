<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Resources\Support\SupportRequestResource;
use App\Services\Support\SupportAccess;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportApiController extends Controller
{
    public function index(string $project, SupportAccess $access): AnonymousResourceCollection
    {
        return SupportRequestResource::collection($access->project($project)->requests()->latest()->paginate(25));
    }

    public function show(string $project, string $reference, SupportAccess $access): SupportRequestResource
    {
        return new SupportRequestResource($access->project($project)->requests()->where('reference', $reference)->with('events')->firstOrFail());
    }
}
