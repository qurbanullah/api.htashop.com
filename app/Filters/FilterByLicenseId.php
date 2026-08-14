<?php

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

class FilterByLicenseId
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function handle(Builder $builder, \Closure $next)
    {
        return $next($builder)
            ->when(
                data_get($this->filters, 'license_id'),
                fn ($query) => $query->where('license_id', '=', data_get($this->filters, 'license_id'))
            );
    }
}
