<?php

namespace App\Filters\Feedbacks;

use Illuminate\Database\Eloquent\Builder;

class FeedbackPriorityFilter
{
    protected ?string $priority;

    public function __construct(?string $priority)
    {
        $this->priority = $priority;
    }

    public function __invoke(Builder $query): Builder
    {
        if (!$this->priority) {
            return $query;
        }

        return $query->byPriority($this->priority);
    }
}
