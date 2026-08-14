<?php

namespace App\Actions\Dam;

use App\Models\Dam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class CreateDamAction
{
    public function handle(array $data): Dam
    {
        return DB::transaction(function () use ($data) {
            return Dam::create([
                'uuid' => data_get($data, 'uuid', (string) Str::uuid()),
                'damable_type' => data_get($data, 'damable_type', null),
                'damable_id' => data_get($data, 'damable_id', null),
                'collection_name' => data_get($data, 'collection_name', 'default'),
                'sort_order' => data_get($data, 'sort_order', null),
                'file_name' => data_get($data, 'file_name'),
                'disk' => data_get($data, 'disk', config('dam.default_disk')),
                'bucket' => data_get($data, 'bucket', config('dam.default_bucket')),
                'object_key' => data_get($data, 'object_key'),
                'mime_type' => data_get($data, 'mime_type', null),
                'size' => data_get($data, 'size', null),
                'checksum_sha256' => data_get($data, 'checksum_sha256', null),
                'etag' => data_get($data, 'etag', null),
                'custom_properties' => data_get($data, 'custom_properties', null),
                'metadata' => data_get($data, 'metadata', null),
                'origin_url' => data_get($data, 'origin_url', null),
                'uploaded_by' => data_get($data, 'uploaded_by', null),
                'derived_from_id' => data_get($data, 'derived_from_id', null),
                'version' => data_get($data, 'version', 1),
                'is_current' => data_get($data, 'is_current', true),
            ]);
        });
    }
}
