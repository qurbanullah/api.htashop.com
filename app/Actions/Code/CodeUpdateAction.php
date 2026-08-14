<?php

namespace App\Actions\Code;

use App\Models\Code;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CodeUpdateAction
{
    public function handle(Code $code, array $data): Code
    {
        return DB::transaction(function () use ($code, $data): Code {
            $codeable = null;

            if (array_key_exists('codeable_type', $data) || array_key_exists('codeable_uuid', $data)) {
                $codeable = $this->resolveCodeable(
                    data_get($data, 'codeable_type', $this->typeAlias($code->codeable_type)),
                    data_get($data, 'codeable_uuid', data_get($code->codeable, 'uuid'))
                );
            }

            if (data_get($data, 'is_primary', $code->is_primary)) {
                ($codeable ?? $code->codeable)->codes()
                    ->where('type', data_get($data, 'type', $code->type))
                    ->where('organization_id', data_get($data, 'organization_id', $code->organization_id))
                    ->where('id', '!=', $code->id)
                    ->update(['is_primary' => false]);
            }

            $attributes = [
                'tenant_id' => data_get($data, 'tenant_id', $code->tenant_id),
                'organization_id' => array_key_exists('organization_id', $data) ? data_get($data, 'organization_id') : $code->organization_id,
                'type' => data_get($data, 'type', $code->type),
                'value' => data_get($data, 'value', $code->value),
                'normalized' => array_key_exists('value', $data) ? $this->normalize(data_get($data, 'value')) : $code->normalized,
                'context' => array_key_exists('context', $data) ? data_get($data, 'context') : $code->context,
                'is_primary' => data_get($data, 'is_primary', $code->is_primary),
                'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $code->metadata,
            ];

            if ($codeable) {
                $attributes['codeable_type'] = $codeable::class;
                $attributes['codeable_id'] = $codeable->id;
            }

            $code->update($attributes);

            return $code->load(['tenant', 'organization', 'codeable']);
        });
    }

    private function resolveCodeable(string $type, string $uuid): Model
    {
        return match ($type) {
            'product' => Product::query()->where('uuid', $uuid)->firstOrFail(),
            'variant' => Variant::query()->where('uuid', $uuid)->firstOrFail(),
            default => throw new \InvalidArgumentException('Unsupported codeable type.'),
        };
    }

    private function typeAlias(string $modelClass): string
    {
        return match ($modelClass) {
            Product::class => 'product',
            Variant::class => 'variant',
            default => throw new \InvalidArgumentException('Unsupported codeable model.'),
        };
    }

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
