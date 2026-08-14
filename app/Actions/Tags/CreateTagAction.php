<?php

namespace App\Actions\Tags;

use App\Models\Tag;
use Illuminate\Support\Str;

class CreateTagAction
{
    public function execute(array $data): Tag
    {
        $name = data_get($data, 'name');
        $slug = data_get($data, 'slug', Str::slug($name));
        $type = data_get($data, 'type', 'product');

        return Tag::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'usage_count' => 0,
        ]);
    }
}
