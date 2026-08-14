<?php

namespace App\Actions\Eula;

use App\Models\Eula;
use Illuminate\Support\Arr;

class UpdateEulaAction
{
    /**
     * Execute the action to update an existing EULA.
     */
    public function execute(Eula $eula, array $data): Eula
    {
        $eula->update([
            'version' => Arr::get($data, 'version', $eula->version),
            'software_id' => Arr::get($data, 'software_id', $eula->software_id),
            'version_id' => Arr::get($data, 'version_id', $eula->version_id),
            'title' => Arr::get($data, 'title', $eula->title),
            'content' => Arr::get($data, 'content', $eula->content),
            'status' => Arr::get($data, 'status', $eula->status),
            'effective_date' => Arr::get($data, 'effective_date', $eula->effective_date),
            'updated_by' => Arr::get($data, 'updated_by'),
        ]);

        return $eula->fresh();
    }
}
