<?php

namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

class FilterByLicenseAnalyticSearch
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function handle(Builder $builder, \Closure $next)
    {
        $search = data_get($this->filters, 'search');

        if (empty($search)) {
            return $next($builder);
        }

        return $next($builder)->where(function ($query) use ($search) {
            // Search by event_name
            $query->where('event_name', 'like', '%' . $search . '%')
                // Search by event_type
                ->orWhere('event_type', 'like', '%' . $search . '%')
                // Search by license_id
                ->orWhere('license_id', '=', $search)
                // Search by app_user_uuid
                ->orWhere('app_user_uuid', 'like', '%' . $search . '%')
                // Search by IP address
                ->orWhere('ip_address', 'like', '%' . $search . '%')
                // Search in license relationship (user name/email)
                ->orWhereHas('license.userWithTrashed', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
        });
    }
}
