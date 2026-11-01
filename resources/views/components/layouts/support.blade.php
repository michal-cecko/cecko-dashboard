<!doctype html>
<html class="scheme-light-dark" lang="{{ $locale ?? app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="same-origin"><meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#020617" media="(prefers-color-scheme: dark)"><title>{{ $projectName ?? $project->name ?? __('support.portal') }} · ČEČKO.dev</title>@vite(['resources/css/app.css', 'resources/js/app.js'])@livewireStyles</head>
<body class="[&_button]:touch-manipulation [&_a]:touch-manipulation [&_button]:focus-visible:outline-2 [&_button]:focus-visible:outline-offset-2 [&_button]:focus-visible:outline-teal-600 [&_a]:focus-visible:outline-2 [&_a]:focus-visible:outline-offset-2 [&_a]:focus-visible:outline-teal-600 tabular-nums min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<a href="#content" class="sr-only focus:not-sr-only focus:block focus:p-4">{{ __('support.skip') }}</a>
<div class="mx-auto max-w-3xl px-4 py-6 sm:py-10">
<header class="mb-8 flex items-center justify-between gap-4"><a href="https://cecko.dev" class="text-lg font-semibold tracking-tight">ČEČKO<span class="text-teal-600 dark:text-teal-300">.dev</span></a><span class="text-sm text-slate-500 dark:text-slate-400">{{ $projectName ?? $project->name ?? '' }}</span></header>
@if (isset($projectSlug))
<nav aria-label="{{ __('support.navigation') }}" class="mb-8 flex flex-wrap gap-2">
@foreach (['home' => 'requests', 'plan' => 'plan', 'invoices' => 'invoices', 'reports' => 'reports'] as $route => $label)
<a href="{{ route('support.'.$route, $projectSlug) }}" @if(request()->routeIs('support.'.$route)) aria-current="page" @endif class="rounded-xl px-4 py-3 text-sm font-medium hover:bg-slate-200 focus-visible:outline-2 focus-visible:outline-teal-600 aria-[current=page]:bg-teal-100 aria-[current=page]:text-teal-900 dark:hover:bg-slate-800 dark:aria-[current=page]:bg-teal-950 dark:aria-[current=page]:text-teal-200">{{ __('support.'.$label) }}</a>
@endforeach
<form method="POST" action="{{ route('support.logout', $projectSlug) }}" class="ml-auto">@csrf<button class="rounded-xl px-4 py-3 text-sm hover:bg-slate-200 dark:hover:bg-slate-800">{{ __('support.logout') }}</button></form>
</nav>
@endif
<main id="content">{{ $slot }}</main>
<footer class="mt-12 border-t border-slate-200 pt-5 text-xs text-slate-500 dark:border-slate-800">{{ __('support.footer') }}</footer>
</div>@livewireScripts
</body></html>
