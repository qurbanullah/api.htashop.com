<?php

namespace App\Actions\Features;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DetachFeaturesAction
{
    public function execute(Model $featurable, ?array $featureIds = null): void
    {
        if ($featureIds === null) {
            // Detach all features and decrement usage
            $featurable->features->each(function ($feature) {
                $feature->decrementUsage();
            });

            $featurable->features()->detach();
        } else {
            // Detach specific features and decrement usage
            foreach ($featureIds as $featureId) {
                $feature = Feature::find($featureId);
                $feature?->decrementUsage();
            }

            $featurable->features()->detach($featureIds);
        }
    }
}
