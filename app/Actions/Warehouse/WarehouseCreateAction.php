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
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $address[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        // Legacy flat country name column stays on the warehouse record itself.
        if (array_key_exists('country', $data)) {
            unset($data['country']);
        }

        return $address;
    }
}
