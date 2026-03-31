<?php

use App\Http\Controllers\Songs\SongController;
use App\Http\Controllers\Songs\SongGenreController;
use App\Http\Controllers\Songs\SongTagController;

Route::apiResource('songs', SongController::class)->only(['index']);
Route::apiResource('song-tags', SongTagController::class)->only(['index']);
Route::apiResource('song-genres', SongGenreController::class)->only(['index']);
