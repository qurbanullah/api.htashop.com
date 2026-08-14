<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Feature
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Actions\Features;

use App\Models\Feature;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Pipeline;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Feature
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeatureShowAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  model values
     */
    public function handle(array $data): LengthAwarePaginator
    {
        return Pipeline::send(Feature::query())
            ->through(
                [
                    new \App\Filters\FilterByIsActive(data_get($data, 'filters')),
                    new \App\Filters\FilterByName(data_get($data, 'filters')),
                ]
            )
            ->thenReturn()
            ->select('*')
            ->orderBy('sorting', 'asc')
            ->paginate(data_get($data, 'rows_per_page'));
    }
}
