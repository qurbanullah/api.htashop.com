<?php

namespace App\Actions\Label;

use App\Models\Label;
use Illuminate\Support\Str;

class LabelUpdateAction
{
    public function handle(Label $label, array $data): Label
    {
        $label->update([
            'tenant_id' => data_get($data, 'tenant_id', $label->tenant_id),
            'slug' => array_key_exists('slug', $data)
                ? $this->resolveSlug($label, data_get($data, 'slug'), data_get($data, 'display_name', $label->name), data_get($data, 'tenant_id', $label->tenant_id))
                : $label->slug,
            'name' => data_get($data, 'display_name', $label->name),
            'summary' => array_key_exists('summary', $data) ? data_get($data, 'summary') : $label->summary,
            'description' => array_key_exists('description', $data) ? data_get($data, 'description') : $label->description,
            'image' => array_key_exists('image', $data) ? data_get($data, 'image') : $label->image,
            'sorting' => array_key_exists('sorting', $data) ? data_get($data, 'sorting') : $label->sorting,
            'is_active' => data_get($data, 'is_active', $label->is_active),
            'metadata' => array_key_exists('metadata', $data) ? data_get($data, 'metadata') : $label->metadata,
        ]);

        return $label->refresh();
    }

    private function resolveSlug(Label $label, ?string $slug, string $displayName, ?int $tenantId): string
    {
        $candidate = Str::slug($slug ?: $displayName, '_');
        $base = $candidate;
        $suffix = 1;

        $query = Label::query()->where('slug', $candidate)->where('id', '!=', $label->id);
        if (is_null($tenantId)) {
            $query->whereNull('tenant_id');
        } else {
            $query->where('tenant_id', $tenantId);
        }

        while ($query->exists()) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
            $query = Label::query()->where('slug', $candidate)->where('id', '!=', $label->id);
            if (is_null($tenantId)) {
                $query->whereNull('tenant_id');
            } else {
                $query->where('tenant_id', $tenantId);
            }
        }

        return $candidate;
    }
}
