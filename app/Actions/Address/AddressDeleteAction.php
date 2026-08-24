<?php

namespace App\Actions\Address;

use App\Models\Address;

class AddressDeleteAction
{
    public function handle(Address $address): void
    {
        $address->delete();
    }
}
