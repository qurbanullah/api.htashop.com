<?php

declare(strict_types=1);

/**
 * Filter by license search class file.
 * php version 8.4
 *
 * @category  App\Filters
 * @package   App\Filters\FilterByLicenseSearch
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @version   GIT: <git_id>
 * @link      https://github.com/qurbanullah
 */
namespace App\Filters;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filter by license search class.
 *
 * @category App\Filters
 * @package  App\Filters\FilterByLicenseSearch
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class FilterByLicenseSearch
{
    /**
     * Class constructor
     *
     * @param array $filters Query filter
     *
     * @return void
     */
    public function __construct(protected array $filters)
    {

    }

    /**
     * Filter records by search query.
     *
     * @param Builder  $builder Query builder
     * @param \Closure $next    Closure
     *
     * @return \Closure
     */
    public function handle(Builder $builder, \Closure $next)
    {
        return $next($builder)
            ->when(
                isset($this->filters['query']) && !empty($this->filters['query']),
                fn ($query) => $query->where(function ($q) {
                    $searchTerm = data_get($this->filters, 'query');
                    $q->where('license_key', 'like', "%{$searchTerm}%")
                        ->orWhere('hardware_id', 'like', "%{$searchTerm}%")
                        ->orWhere('user_message', 'like', "%{$searchTerm}%")
                        ->orWhere('admin_message', 'like', "%{$searchTerm}%");
                })
            );

    }
}
