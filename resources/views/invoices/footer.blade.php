<!DOCTYPE html>
<html>
<head>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8px;
            color: #9ca3af;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td {
            padding: 10px 40px 0 40px;
            border-top: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        td.right {
            text-align: right;
        }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                @if(!empty($responsiblePerson))
                    {{ __('invoice.printed_by') }}: {{ $responsiblePerson }}, {{ now()->format('d.m.Y H:i:s') }}
                @else
                    {{ now()->format('d.m.Y H:i:s') }}
                @endif
            </td>
            <td class="right">
                {{ __('invoice.page') }} <span class="pageNumber"></span>/<span class="totalPages"></span>
            </td>
        </tr>
    </table>
</body>
</html>
