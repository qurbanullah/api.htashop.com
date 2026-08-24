<?php

namespace App\Actions\Address;

use App\Models\Address;

class AddressSetPrimaryAction
{
    public function handle(Address $address): Address
    {
        if ($address->is_primary) {
            return $address;
        }

        Address::query()
            ->where('addressable_type', $address->addressable_type)
            ->where('addressable_id', $address->addressable_id)
            ->where('type', $address->type)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        $address->update(['is_primary' => true]);

        return $address;
    }
}
