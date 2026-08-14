<?php

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

class FilterByLicenseAnalyticEventName
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function handle(Builder $builder, \Closure $next)
    {
        $eventName = data_get($this->filters, 'event_name');
        $eventType = data_get($this->filters, 'event_type');

        if (!empty($eventName)) {
            $builder = $builder->where('event_name', '=', $eventName);
        }

        if (!empty($eventType)) {
            $builder = $builder->where('event_type', '=', $eventType);
        }

        return $next($builder);
    }
}
