<?php

/**
 * Flter by name class file.
 * php version 8.3
 *
 * @category  App\Filters
 * @package   App\Filters\FilterByBusinessId
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
 * Flter by name class.
 *
 * @category App\Filters
 * @package  App\Filters\FilterByBusinessId
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class FilterByBusinessId
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
                data_get($this->filters, 'business_id'),
                fn ($query) => $query->where('business_id', '=', data_get($this->filters, 'business_id'))
            );

    }
}
