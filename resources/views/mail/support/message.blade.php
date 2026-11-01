<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"></head><body>
<h1>{{ $heading }}</h1>
@foreach ($lines as $line)<p style="white-space: pre-line">{{ $line }}</p>@endforeach
@if ($actionUrl)<p><a href="{{ $actionUrl }}">{{ __('support.open') }}</a></p>@endif
</body></html>
