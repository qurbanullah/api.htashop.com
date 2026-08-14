<?php

namespace App\Actions\Code;

use App\Models\Code;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CodeCreateAction
{
    public function handle(array $data): Code
    {
        return DB::transaction(function () use ($data): Code {
            $codeable = $this->resolveCodeable(data_get($data, 'codeable_type'), data_get($data, 'codeable_uuid'));

            if (data_get($data, 'is_primary', false)) {
                $codeable->codes()
                    ->where('type', data_get($data, 'type'))
                    ->where('organization_id', data_get($data, 'organization_id'))
                    ->update(['is_primary' => false]);
            }

            $code = $codeable->codes()->create([
                'tenant_id' => data_get($data, 'tenant_id'),
                'organization_id' => data_get($data, 'organization_id'),
                'type' => data_get($data, 'type'),
                'value' => data_get($data, 'value'),
                'normalized' => $this->normalize(data_get($data, 'value')),
                'context' => data_get($data, 'context'),
                'is_primary' => data_get($data, 'is_primary', false),
                'metadata' => data_get($data, 'metadata'),
            ]);

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

    private function normalize(string $value): string
    {
        return mb_strtoupper(trim($value));
    }
}
