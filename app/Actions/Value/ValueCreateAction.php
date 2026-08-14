<?php

namespace App\Actions\Value;

use App\Models\Definition;
use App\Support\Punchout\ValuableTypeRegistry;
use App\Models\Unit;
use App\Models\Value;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ValueCreateAction
{
    public function handle(array $data): Value
    {
        return DB::transaction(function () use ($data): Value {
            $valuable = $this->resolveValuable(data_get($data, 'valuable_type'), data_get($data, 'valuable_uuid'));
            $definition = Definition::query()->findOrFail(data_get($data, 'definition_id'));
            $unit = data_get($data, 'unit_id') ? Unit::query()->find(data_get($data, 'unit_id')) : null;

            $value = $valuable->values()->create([
                'tenant_id' => data_get($data, 'tenant_id'),
                'definition_id' => $definition->id,
                'option_id' => data_get($data, 'option_id'),
                'unit_id' => data_get($data, 'unit_id'),
                'value_text' => data_get($data, 'value_text'),
                'value_number' => data_get($data, 'value_number'),
                'value_boolean' => data_get($data, 'value_boolean'),
                'value_date' => data_get($data, 'value_date'),
                'value_datetime' => data_get($data, 'value_datetime'),
                'value_json' => data_get($data, 'value_json'),
                'normalized_number' => $this->normalizeNumber(data_get($data, 'value_number'), $definition, $unit),
                'locale' => data_get($data, 'locale'),
                'channel' => data_get($data, 'channel'),
                'metadata' => data_get($data, 'metadata'),
            ]);

            return $value->load(['tenant', 'definition', 'option', 'unit', 'valuable']);
        });
    }

    private function resolveValuable(string $type, string $uuid): Model
    {
        return ValuableTypeRegistry::resolveModelOrFail($type, $uuid);
    }

    private function normalizeNumber(mixed $value, Definition $definition, ?Unit $unit): ?float
    {
        if (is_null($value) || !$unit || !$definition->measurement_id) {
            return is_null($value) ? null : (float) $value;
        }

        return ((float) $value + (float) $unit->offset) * (float) $unit->factor;
    }
}
