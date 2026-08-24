<?php

namespace App\Actions\Address;

use App\Models\Address;
use Illuminate\Database\Eloquent\Model;

class AddressCreateAction
{
    public function handle(Model $owner, array $data): Address
    {
        $data['is_primary'] = (bool) data_get($data, 'is_primary', false);

        // First address of a given type automatically becomes primary.
        if (! $data['is_primary']) {
            $data['is_primary'] = ! $owner->addresses()
                ->where('type', $data['type'] ?? 'shipping')
                ->exists();
        }

        return $owner->addresses()->create($data);
    }
}
