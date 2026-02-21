<?php

namespace App\Filament\Invoices\Resources\Customers\Pages;

use App\Filament\Invoices\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected static ?string $title = 'Nový odberateľ';
}
