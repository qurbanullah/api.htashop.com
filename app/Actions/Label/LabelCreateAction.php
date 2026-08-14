<?php

namespace App\Actions\Label;

use App\Models\Label;
use Illuminate\Support\Str;

class LabelCreateAction
{
    public function handle(array $data): Label
    {
        return Label::create([
            'tenant_id' => data_get($data, 'tenant_id'),
            'slug' => $this->resolveSlug(data_get($data, 'slug'), data_get($data, 'display_name'), data_get($data, 'tenant_id')),
            'name' => data_get($data, 'display_name'),
            'summary' => data_get($data, 'summary'),
            'description' => data_get($data, 'description'),
            'image' => data_get($data, 'image'),
            'sorting' => data_get($data, 'sorting'),
            'is_active' => data_get($data, 'is_active', true),
            'metadata' => data_get($data, 'metadata'),
        ]);
    }

    private function resolveSlug(?string $slug, string $displayName, ?int $tenantId): string
    {
        $candidate = Str::slug($slug ?: $displayName, '_');
        $base = $candidate;
        $suffix = 1;

        $query = Label::query()->where('slug', $candidate);
        if (is_null($tenantId)) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        while ($query->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            $query = Label::query()->where('slug', $candidate);
            if (is_null($tenantId)) {
                $query->whereNull('tenant_id');
            } else {
                $query->where('tenant_id', $tenantId);
            }
        }

        return $candidate;
    }
}
