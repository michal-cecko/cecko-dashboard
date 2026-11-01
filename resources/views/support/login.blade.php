<x-layouts.support :project="$project" :locale="$project->locale">
<div class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-8 dark:border-slate-800 dark:bg-slate-900">
<h1 class="text-2xl font-semibold">{{ __('support.login') }}</h1><p class="mt-3 text-slate-600 dark:text-slate-300">{{ __('support.login_help') }}</p>
@if(session('status'))<p role="status" class="mt-5 rounded-xl bg-teal-50 p-4 text-teal-900 dark:bg-teal-950 dark:text-teal-200">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('support.login.send', $project->slug) }}" class="mt-6 grid gap-5">@csrf
<div><label for="email" class="mb-2 block font-medium">E-mail</label><input id="email" name="email" type="email" spellcheck="false" autocapitalize="none" autocomplete="email" required maxlength="255" value="{{ old('email') }}" class="w-full rounded-xl border border-slate-300 px-4 py-3 dark:border-slate-700 dark:bg-slate-950">@error('email')<p role="alert" class="mt-2 text-red-600">{{ $message }}</p>@enderror</div>
<div class="cf-turnstile" data-sitekey="{{ config('support.turnstile.site_key') }}"></div>@error('cf-turnstile-response')<p role="alert" class="text-red-600">{{ $message }}</p>@enderror
<button class="rounded-xl bg-teal-700 px-5 py-3 font-semibold text-white hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">{{ __('support.send_link') }}</button>
</form></div><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</x-layouts.support>
