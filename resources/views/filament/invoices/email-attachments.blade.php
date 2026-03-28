@use('Illuminate\Support\Facades\Storage')

@php
    $attachments = $getRecord()->attachments ?? [];
@endphp

@if(empty($attachments))
    <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
@else
    <div class="flex flex-wrap gap-2">
        @foreach($attachments as $attachment)
            @php
                $name = $attachment['name'] ?? 'Súbor';
                $hasPath = isset($attachment['path']);
                $mime = $attachment['mime'] ?? '';
                $isImage = str_starts_with($mime, 'image/');
                $isPdf = $mime === 'application/pdf';
            @endphp

            @if($hasPath)
                <a
                    href="{{ Storage::disk('private')->temporaryUrl($attachment['path'], now()->addMinutes(30)) }}"
                    target="_blank"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    @if($isPdf)
                        <x-heroicon-m-document style="width:16px;height:16px" class="shrink-0 text-red-500" />
                    @elseif($isImage)
                        <x-heroicon-m-photo style="width:16px;height:16px" class="shrink-0 text-blue-500" />
                    @else
                        <x-heroicon-m-paper-clip style="width:16px;height:16px" class="shrink-0 text-gray-400" />
                    @endif
                    {{ $name }}
                </a>
            @else
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    @if($isPdf)
                        <x-heroicon-m-document style="width:16px;height:16px" class="shrink-0 text-red-500" />
                    @else
                        <x-heroicon-m-paper-clip style="width:16px;height:16px" class="shrink-0 text-gray-400" />
                    @endif
                    {{ $name }}
                </span>
            @endif
        @endforeach
    </div>
@endif
