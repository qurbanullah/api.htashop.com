<?php

namespace App\Actions\Warehouse;

use App\Models\Warehouse;

class WarehouseCreateAction
{
    public function handle(array $data): Warehouse
    {
        $addressData = $this->extractAddress($data);

        $warehouse = Warehouse::create($data);

        if (! empty($addressData)) {
            $warehouse->address()->create([
                ...$addressData,
                'type' => 'warehouse',
                'is_primary' => true,
            ]);
        }

        return $warehouse->load('address.country');
    }

    protected function extractAddress(array &$data): array
    {
        $keys = [
            'contact_name', 'email', 'phone',
            'address_line_1', 'address_line_2', 'city', 'city_id',
            'state', 'state_code', 'postal_code', 'country_id',
        ];

        $address = [];

        // Contact and address details live on the `address` row. These keys also
        // exist as flat columns on `warehouses`, so they are kept in `$data` and
        // written to both — reports and raw SQL still see the values, and there is
        // no second, silently stale copy.
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
