<x-mail::message :logo-url="$logoUrl ?? null" :company-name="$sellerName ?? null">
{{ $body }}

@if($invoiceNumber)
<x-mail::table>
| | |
|:--|--:|
| **Faktúra** | {{ $invoiceNumber }} |
@if($issueDate)
| **Vystavená** | {{ $issueDate }} |
@endif
@if($dueDate)
| **Splatnosť** | {{ $dueDate }} |
@endif
@if($totalFormatted)
| **Celkom** | **{{ $totalFormatted }}** |
@endif
</x-mail::table>
@endif

<x-mail::panel>
PDF faktúra je priložená k tomuto emailu.
</x-mail::panel>

S pozdravom,<br>
{{ $sellerName ?? config('app.name') }}
</x-mail::message>
