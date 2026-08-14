<?php

namespace App\Filters\Feedbacks;

use Illuminate\Database\Eloquent\Builder;

class FeedbackTypeFilter
{
    protected ?string $type;

    public function __construct(?string $type)
    {
        $this->type = $type;
    }

    public function __invoke(Builder $query): Builder
    {
        if (!$this->type) {
            return $query;
        }

        return $query->byType($this->type);
    }
}
