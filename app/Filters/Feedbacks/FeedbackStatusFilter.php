<?php

namespace App\Filters\Feedbacks;

use Illuminate\Database\Eloquent\Builder;

class FeedbackStatusFilter
{
    protected ?string $status;

    public function __construct(?string $status)
    {
        $this->status = $status;
    }

    public function __invoke(Builder $query): Builder
    {
        if (!$this->status) {
            return $query;
        }

        return $query->byStatus($this->status);
    }
}
