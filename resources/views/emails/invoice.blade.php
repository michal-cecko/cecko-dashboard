<x-mail::message>
{{ $body }}

<x-mail::button :url="config('app.url')">
Otvoriť faktúry
</x-mail::button>

S pozdravom,<br>
{{ config('app.name') }}
</x-mail::message>
