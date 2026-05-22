<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $fileShare->title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl dark:text-white">
                {{ $fileShare->title }}
            </h1>
            @if($fileShare->description)
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{ $fileShare->description }}
                </p>
            @endif
            @if($fileShare->expires_at)
                <p class="mt-3 text-sm text-amber-600 dark:text-amber-400">
                    Odkaz vyprší {{ $fileShare->expires_at->diffForHumans() }}
                </p>
            @endif
        </div>

        @php($files = $fileShare->getMedia('files'))

        @if($files->isNotEmpty())
            @if($files->count() > 1)
                <div class="mb-4 flex justify-end">
                    <a href="{{ route('file-share.download-all', $fileShare->share_token) }}"
                       class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Stiahnuť všetko (ZIP)
                    </a>
                </div>
            @endif

            <ul class="divide-y divide-gray-200 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                @foreach($files as $media)
                    <li class="flex items-center gap-4 px-4 py-3 sm:px-6">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>

                        <div class="min-w-0 flex-1">
                            @php($ext = pathinfo($media->file_name, PATHINFO_EXTENSION))
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                                {{ $media->name ? ($ext ? $media->name.'.'.$ext : $media->name) : $media->file_name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ \Illuminate\Support\Number::fileSize($media->size) }}
                            </p>
                        </div>

                        <a href="{{ route('file-share.download', [$fileShare->share_token, $media->id]) }}"
                           class="inline-flex shrink-0 items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Stiahnuť
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
                <p class="mt-4 text-gray-500 dark:text-gray-400">Žiadne súbory</p>
            </div>
        @endif
    </div>
</body>
</html>
