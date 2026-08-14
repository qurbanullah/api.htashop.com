<?php

namespace App\Actions\Specifications;

use App\Models\Specification;
use Illuminate\Database\Eloquent\Model;

class DetachSpecificationsAction
{
    public function execute(Model $specifiable, ?array $specificationIds = null): void
    {
        if ($specificationIds === null) {
            // Detach all specifications and decrement usage
            $specifiable->specifications->each(function ($specification) {
                $specification->decrementUsage();
            });

            $specifiable->specifications()->detach();
        } else {
            // Detach specific specifications and decrement usage
            foreach ($specificationIds as $specificationId) {
                $specification = Specification::find($specificationId);
                $specification?->decrementUsage();
            }

            $specifiable->specifications()->detach($specificationIds);
        }
    }
}
