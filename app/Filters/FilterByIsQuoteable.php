<?php

/**
 * Filter by Quoteable status.
 * php version 8.3
 *
 * @category  App\Filters
 * @package   App\Filters\FilterByIsQuoteable
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

use Illuminate\Database\Eloquent\Builder;

/**
 * Filter by Quoteable status.
 *
 * @category App\Filters
 * @package  App\Filters\FilterByIsQuoteable
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class FilterByIsQuoteable
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
     * Update record in database.
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
                isset($this->filters['is_quoteable']),
                fn ($query) => $query->where('is_quoteable', (bool) data_get($this->filters, 'is_quoteable'))
            );
    }
}
