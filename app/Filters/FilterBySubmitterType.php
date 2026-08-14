<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

class FilterBySubmitterType
{
    public function __construct(protected array $filters)
    {
    }

    public function handle(Builder $builder, \Closure $next)
    {
        return $next($builder)
            ->when(
                data_get($this->filters, 'submitter_type') === 'portal',
                fn ($query) => $query->whereNotNull('user_id')
            )
            ->when(
                data_get($this->filters, 'submitter_type') === 'website',
                fn ($query) => $query->whereNull('user_id')
            );
    }
}
