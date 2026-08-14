<?php

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filter by software_id class.
 */
class FilterBySoftwareId
{
    /**
     * Class constructor
     *
     * @param array $filters Query filter
     */
    public function __construct(protected array $filters)
    {
    }

    /**
     * Handle the filter.
     *
     * @param Builder  $builder Query builder
     * @param \Closure $next    Closure
     *
     * @return mixed
     */
    public function handle(Builder $builder, \Closure $next)
    {
        return $next($builder)
            ->when(
                data_get($this->filters, 'software_id'),
                fn ($query) => $query->where('software_id', data_get($this->filters, 'software_id'))
            );
    }
}
