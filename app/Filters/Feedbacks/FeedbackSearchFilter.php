<?php

namespace App\Filters\Feedbacks;

use Illuminate\Database\Eloquent\Builder;

class FeedbackSearchFilter
{
    protected ?string $search;

    public function __construct(?string $search)
    {
        $this->search = $search;
    }

    public function __invoke(Builder $query): Builder
    {
        if (!$this->search) {
            return $query;
        }

        return $query->search($this->search);
    }
}
