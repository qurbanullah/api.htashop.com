<?php

namespace App\Actions\Specifications;

use App\Models\Specification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SyncSpecificationsAction
{
    public function execute(Model $specifiable, array|Collection $specifications, string $type = 'product'): Collection
    {
        $specifications = is_array($specifications) ? collect($specifications) : $specifications;

        // Get old specification IDs before sync
        $oldSpecIds = $specifiable->specifications->pluck('id')->toArray();

        $specificationModels = $specifications->map(function ($spec) use ($type) {
            // Handle different input formats
            if ($spec instanceof Specification) {
                return $spec;
            }

            // If it's an array with id/name and value
            if (is_array($spec)) {
                $specId = data_get($spec, 'id');
                $specName = data_get($spec, 'name');
                $value = data_get($spec, 'value');
                $munitId = data_get($spec, 'munit_id'); // Get munit_id from input

                if ($specId) {
                    $specModel = Specification::find($specId);
                    if ($specModel) {
                        $specModel->pivot_value = $value;
                        $specModel->pivot_munit_id = $munitId; // Store munit_id
                        return $specModel;
                    }
                }

                if ($specName) {
                    $specModel = Specification::firstOrCreate(
                        ['slug' => Str::slug($specName), 'type' => $type],
                        [
                            'name' => $specName,
                            'usage_count' => 0,
                            'group' => data_get($spec, 'group'),
                        ]
                    );
                    $specModel->pivot_value = $value;
                    $specModel->pivot_munit_id = $munitId; // Store munit_id
                    return $specModel;
                }

                return null;
            }

            // If it's numeric ID
            if (is_numeric($spec)) {
                return Specification::find($spec);
            }

            return null;
        })->filter();

        // Prepare sync data with values and munit_id
        $syncData = [];
        foreach ($specificationModels as $specModel) {
            $pivotData = [
                'value' => $specModel->pivot_value ?? ''
            ];

            // Add munit_id if provided
            if (isset($specModel->pivot_munit_id)) {
                $pivotData['munit_id'] = $specModel->pivot_munit_id;
            }

            $syncData[$specModel->id] = $pivotData;
        }

        // Sync specifications
        $specifiable->specifications()->sync($syncData);

        // Get new specification IDs after sync
        $newSpecIds = $specificationModels->pluck('id')->toArray();

        // Decrement usage for removed specifications
        $removedSpecIds = array_diff($oldSpecIds, $newSpecIds);
        foreach ($removedSpecIds as $specId) {
            $spec = Specification::find($specId);
            $spec?->decrementUsage();
        }

        // Increment usage for new specifications
        $addedSpecIds = array_diff($newSpecIds, $oldSpecIds);
        foreach ($addedSpecIds as $specId) {
            $spec = Specification::find($specId);
            $spec?->incrementUsage();
        }

        return $specificationModels;
    }
}
