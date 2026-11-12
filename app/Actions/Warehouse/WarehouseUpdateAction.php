<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseUpdateAction
{
    public function handle(Warehouse $warehouse, array $data): Warehouse
    {
        $addressData = $this->extractAddress($data);

        $warehouse->update($data);

        if (! empty($addressData)) {
            $warehouse->address()->updateOrCreate(
                ['type' => 'warehouse'],
                [
                    ...$addressData,
                    'type' => 'warehouse',
                    'is_primary' => true,
                ],
            );
        }

        return $warehouse->fresh('address.country');
    }

    protected function extractAddress(array &$data): array
    {
        $keys = [
            'contact_name', 'email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'city_id',
            'state', 'state_code', 'postal_code', 'country_id',
        ];

        $address = [];

        // Mirrored onto the flat warehouse columns as well; see the create action.
        $mirrored = [
            'contact_name', 'email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
        ];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $address[$key] = $data[$key];

            if (! in_array($key, $mirrored, true)) {
                unset($data[$key]);
            }
        }

        return $address;
    }
}
