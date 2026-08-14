<?php

namespace App\Actions\Features;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AttachFeaturesAction
{
    public function execute(Model $featurable, array|Collection $features, string $type = 'product'): Collection
    {
        $features = is_array($features) ? collect($features) : $features;

        $featureModels = $features->map(function ($feature) use ($type) {
            // Handle different input formats
            if ($feature instanceof Feature) {
                return $feature;
            }

            // If it's an array with id and value
            if (is_array($feature)) {
                $featureId = data_get($feature, 'id');
                $featureName = data_get($feature, 'name');
                $value = data_get($feature, 'value');

                if ($featureId) {
                    $featureModel = Feature::find($featureId);
                    if ($featureModel) {
                        $featureModel->pivot_value = $value;
                        return $featureModel;
                    }
                }

                if ($featureName) {
                    $featureModel = Feature::firstOrCreate(
                        ['slug' => Str::slug($featureName), 'type' => $type],
                        ['name' => $featureName, 'usage_count' => 0]
                    );
                    $featureModel->pivot_value = $value;
                    return $featureModel;
                }

                return null;
            }

            // If it's numeric ID
            if (is_numeric($feature)) {
                return Feature::find($feature);
            }

            // If it's a string (feature name)
            return Feature::firstOrCreate(
                ['slug' => Str::slug($feature), 'type' => $type],
                ['name' => $feature, 'usage_count' => 0]
            );
        })->filter();

        // Prepare sync data with values
        $syncData = [];
        foreach ($featureModels as $featureModel) {
            $syncData[$featureModel->id] = [
                'value' => $featureModel->pivot_value ?? null
            ];
        }

        $featurable->features()->syncWithoutDetaching($syncData);

        // Increment usage count
        $featureModels->each(function ($feature) {
            if (!isset($feature->already_counted)) {
                $feature->incrementUsage();
            }
        });

        return $featureModels;
    }
}
