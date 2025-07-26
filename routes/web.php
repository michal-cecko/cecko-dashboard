<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(route("filament.songs.home"));
});

// Simple working route for now
Route::get('/test', function () {
    echo "OK!";
});
