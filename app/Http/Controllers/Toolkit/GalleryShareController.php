<?php

namespace App\Http\Controllers\Toolkit;

use App\Http\Controllers\Controller;
use App\Models\Toolkit\Gallery;
use Illuminate\Http\Response;
use Illuminate\View\View;

class GalleryShareController extends Controller
{
    public function show(string $token): View|Response
    {
        $gallery = Gallery::with('media')
            ->where('share_token', $token)
            ->firstOrFail();

        if (! $gallery->isAccessible()) {
            return response()->view('toolkit.gallery-expired', [
                'gallery' => $gallery,
            ], 410);
        }

        return view('toolkit.gallery-public', [
            'gallery' => $gallery,
        ]);
    }
}
