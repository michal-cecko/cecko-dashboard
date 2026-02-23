@props(['url', 'logoUrl' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $slot }}" style="max-width: 200px; max-height: 80px; width: auto; height: auto; object-fit: contain;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
