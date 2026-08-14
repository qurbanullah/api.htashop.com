<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Modules
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

namespace App\Actions\Modules;

use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Modules
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class ModuleDeleteAction
{
    /**
     * Validate and create record in database.
     *
     * @param  int  $id  model id
     */
    public function handle(int $id): bool
    {
        return DB::transaction(
            function () use ($id) {
                $model = Module::findOrFail($id);

                if ($model) {
                    $currentSorting = $model->sorting;
                    Module::where('sorting', '>', $currentSorting)->decrement('sorting');

                    return $model->delete(); // Returns true on success, false on failure
                }

                return false; // Return false if the Module wasn't found
            }
        );
    }
}
