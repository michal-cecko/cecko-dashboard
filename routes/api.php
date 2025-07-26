<?php

use App\Http\Controllers\Songs\SongController;

Route::apiResource('songs', SongController::class)->only(["index"]);
