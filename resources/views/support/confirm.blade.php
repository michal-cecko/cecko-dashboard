<x-layouts.support :project="$project" :locale="$project->locale">
<div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"><h1 class="text-2xl font-semibold">{{ $heading }}</h1>
@foreach($lines as $line)<p class="mt-4 whitespace-pre-line">{{ $line }}</p>@endforeach
<form method="POST" action="{{ $action }}" class="mt-6">@csrf<input type="hidden" name="confirm" value="1"><button class="rounded-xl bg-teal-700 px-5 py-3 font-semibold text-white hover:bg-teal-800">{{ __('support.confirm') }}</button></form>
</div></x-layouts.support>
