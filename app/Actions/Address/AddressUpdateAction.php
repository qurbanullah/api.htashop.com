<?php

namespace App\Actions\Address;

use App\Models\Address;

class AddressUpdateAction
{
    public function handle(Address $address, array $data): Address
    {
        $wasPrimary = $address->is_primary;
        $nowPrimary = (bool) data_get($data, 'is_primary', $wasPrimary);

        $address->update($data);

        if ($nowPrimary && ! $wasPrimary) {
            $this->clearOtherPrimaries($address);
        }

        return $address->fresh();
    }

    private function clearOtherPrimaries(Address $address): void
    {
        Address::query()
            ->where('addressable_type', $address->addressable_type)
            ->where('addressable_id', $address->addressable_id)
            ->where('type', $address->type)
            ->where('id', '!=', $address->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
