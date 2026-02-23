@props(['url', 'logoUrl' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ $slot }}" style="max-width: 200px; max-height: 80px;">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
