<?php

namespace App\Http\Requests\V1\Shipping;

use App\Models\ShippingZoneLocation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shape and cross-field rules for zone matchers.
 *
 * The `type` decides which field is mandatory; the unused columns are ignored
 * by the service, so a stray value never widens a zone unexpectedly.
 */
trait ValidatesShippingZoneLocations
{
    /**
     * @return array<string, mixed>
     */
    protected function locationRules(): array
    {
        return [
            'locations' => ['nullable', 'array', 'max:200'],
            'locations.*.type' => [
                'required',
                Rule::in([
                    ShippingZoneLocation::TYPE_COUNTRY,
                    ShippingZoneLocation::TYPE_CITY,
                    ShippingZoneLocation::TYPE_STATE,
                    ShippingZoneLocation::TYPE_POSTAL_PREFIX,
                ]),
            ],
            'locations.*.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'locations.*.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'locations.*.state' => ['nullable', 'string', 'max:100'],
            'locations.*.postal_code_prefix' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('locations', []) as $index => $location) {
                if (! is_array($location)) {
                    continue;
                }

                $this->validateLocation($validator, "locations.{$index}", $location);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $location
     */
    private function validateLocation(Validator $validator, string $key, array $location): void
    {
        $type = $location['type'] ?? null;

        $requiresCountry = in_array($type, [
            ShippingZoneLocation::TYPE_COUNTRY,
            ShippingZoneLocation::TYPE_STATE,
            ShippingZoneLocation::TYPE_POSTAL_PREFIX,
        ], true);

        if ($requiresCountry && blank($location['country_id'] ?? null)) {
            $validator->errors()->add(
                "{$key}.country_id",
                'A country is required for every destination.',
            );
        }

        if ($type === ShippingZoneLocation::TYPE_CITY && blank($location['city_id'] ?? null)) {
            $validator->errors()->add("{$key}.city_id", 'Choose the city for this location.');
        }

        if ($type === ShippingZoneLocation::TYPE_STATE && blank($location['state'] ?? null)) {
            $validator->errors()->add("{$key}.state", 'Enter the province or state name.');
        }

        if ($type === ShippingZoneLocation::TYPE_POSTAL_PREFIX
            && blank($location['postal_code_prefix'] ?? null)) {
            $validator->errors()->add(
                "{$key}.postal_code_prefix",
                'Enter the postal code prefix.',
            );
        }
    }
}
