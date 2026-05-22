<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $gallery->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/css/glightbox.min.css">
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:py-12">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl dark:text-white">
                    {{ $gallery->title }}
                </h1>
                @if($gallery->description)
                    <p class="mt-2 text-gray-600 dark:text-gray-400">
                        {{ $gallery->description }}
                    </p>
                @endif
            </div>

            @if($gallery->getMedia('media')->isNotEmpty())
                <a href="{{ route('gallery.download-all', $gallery->share_token) }}"
                   class="inline-flex shrink-0 items-center gap-2 self-start rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Stiahnuť všetko
                </a>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 md:grid-cols-4">
            @foreach($gallery->getMedia('media') as $media)
                @if(str_starts_with($media->mime_type, 'image/'))
                    <a href="{{ $media->getUrl() }}"
                       class="glightbox group relative aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-800"
                       data-gallery="gallery"
                       @if($media->getCustomProperty('caption')) data-title="{{ $media->getCustomProperty('caption') }}" @endif
                    >
                        <img src="{{ $media->getUrl() }}"
                             alt="{{ $media->getCustomProperty('caption', $media->file_name) }}"
                             class="h-full w-full object-cover transition-transform group-hover:scale-105"
                             loading="lazy">
                    </a>
                @elseif(str_starts_with($media->mime_type, 'video/'))
                    <a href="{{ $media->getUrl() }}"
                       class="glightbox group relative aspect-square overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-800"
                       data-gallery="gallery"
                       data-type="video"
                       data-source="local"
                       @if($media->getCustomProperty('caption')) data-title="{{ $media->getCustomProperty('caption') }}" @endif
                    >
                        <video src="{{ $media->getUrl() }}"
                               class="h-full w-full object-cover"
                               muted
                               preload="metadata"></video>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <svg class="h-12 w-12 text-white drop-shadow-lg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        @if($gallery->getMedia('media')->isEmpty())
            <div class="py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z" />
                </svg>
                <p class="mt-4 text-gray-500 dark:text-gray-400">Galéria je prázdna</p>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/glightbox@3.3.0/dist/js/glightbox.min.js"></script>
    <script>
        const lightbox = GLightbox({ selector: '.glightbox' });
    </script>
</body>
</html>
