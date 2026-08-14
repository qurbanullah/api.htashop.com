<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Changelogs
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

namespace App\Actions\Changelogs;

use App\Models\Changelog;
use Illuminate\Support\Facades\DB;

/**
 * CRUD operation class for Changelog Model.
 *
 * @category App\Actions\Changelogs
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class ChangelogCreateAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  model values
     */
    public function handle(array $data): Changelog
    {
        return DB::transaction(
            function () use ($data) {
                return Changelog::create($data);
            }
        );
    }
}
