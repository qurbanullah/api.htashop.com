<?php

namespace App\Actions\Eula;

use App\Models\Eula;
use Illuminate\Support\Arr;

class CreateEulaAction
{
    /**
     * Execute the action to create a new EULA.
     */
    public function execute(array $data): Eula
    {
        return Eula::create([
            'version' => $data['version'],
            'software_id' => Arr::get($data, 'software_id'),
            'version_id' => Arr::get($data, 'version_id'),
            'title' => $data['title'],
            'content' => $data['content'],
            'status' => Arr::get($data, 'status', 'draft'),
            'effective_date' => Arr::get($data, 'effective_date'),
            'created_by' => Arr::get($data, 'created_by'),
        ]);
    }
}
