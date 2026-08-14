<?php

namespace App\Actions\Value;

use App\Models\Definition;
use App\Support\Punchout\ValuableTypeRegistry;
use App\Models\Unit;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ValueUpdateAction
{
    public function handle(Value $value, array $data): Value
    {
        return DB::transaction(function () use ($value, $data): Value {
            $valuable = null;

            if (array_key_exists('valuable_type', $data) || array_key_exists('valuable_uuid', $data)) {
                $valuable = $this->resolveValuable(
                    data_get($data, 'valuable_type', $this->typeAlias($value->valuable_type)),
                    data_get($data, 'valuable_uuid', data_get($value->valuable, 'uuid'))
                );
            }

            $definition = array_key_exists('definition_id', $data)
                ? Definition::query()->findOrFail(data_get($data, 'definition_id'))
                : $value->definition;

            $unitId = array_key_exists('unit_id', $data) ? data_get($data, 'unit_id') : $value->unit_id;
            $unit = $unitId ? Unit::query()->find($unitId) : null;
            $number = array_key_exists('value_number', $data) ? data_get($data, 'value_number') : $value->value_number;

            $attributes = [
                'tenant_id' => data_get($data, 'tenant_id', $value->tenant_id),
                'definition_id' => $definition->id,
                'option_id' => array_key_exists('option_id', $data) ? data_get($data, 'option_id') : $value->option_id,
                'unit_id' => $unitId,
                'value_text' => array_key_exists('value_text', $data) ? data_get($data, 'value_text') : $value->value_text,
                'value_number' => $number,
                'value_boolean' => array_key_exists('value_boolean', $data) ? data_get($data, 'value_boolean') : $value->value_boolean,
                'value_date' => array_key_exists('value_date', $data) ? data_get($data, 'value_date') : $value->value_date,
                'value_datetime' => array_key_exists('value_datetime', $data) ? data_get($data, 'value_datetime') : $value->value_datetime,
                'value_json' => array_key_exists('value_json', $data) ? data_get($data, 'value_json') : $value->value_json,
                'normalized_number' => $this->normalizeNumber($number, $definition, $unit),
                'locale' => array_key_exists('locale', $data) ? data_get($data, 'locale') : $value->locale,
                'channel' => array_key_exists('channel', $data) ? data_get($data, 'channel') : $value->channel,
                'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $value->metadata,
            ];

            if ($valuable) {
                $attributes['valuable_type'] = $valuable::class;
                $attributes['valuable_id'] = $valuable->id;
            }

            $value->update($attributes);

            return $value->load(['tenant', 'definition', 'option', 'unit', 'valuable']);
        });
    }

    private function resolveValuable(string $type, string $uuid): Model
    {
        return ValuableTypeRegistry::resolveModelOrFail($type, $uuid);
    }

    private function typeAlias(string $modelClass): string
    {
        return ValuableTypeRegistry::aliasForModel($modelClass);
    }

    private function normalizeNumber(mixed $value, Definition $definition, ?Unit $unit): ?float
    {
        if (is_null($value) || !$unit || !$definition->measurement_id) {
            return is_null($value) ? null : (float) $value;
        }

        return ((float) $value + (float) $unit->offset) * (float) $unit->factor;
    }
}
