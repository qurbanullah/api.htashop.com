<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Services\Tags
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

namespace App\Services\Tags;

use App\Helpers\CacheHelper;
use App\Actions\Tags\TagCreateAction;
use App\Actions\Tags\TagDeleteAction;
use App\Actions\Tags\TagSortAction;
use App\Actions\Tags\TagUpdateAction;
use App\Actions\Tags\TagPatchAction;
use App\Actions\Tags\TagSearchByIdAction;
use App\Models\Tag;
/**
 * CRUD operation class for Document Model.
 *
 * @category App\Services\Tags
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TagService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): Tag
    {
        $result = (new TagCreateAction)->handle($data);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Validate and update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): Tag
    {
        $result = (new TagUpdateAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function patch(array $data, int $id): Tag
    {
        $result = (new TagPatchAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Delete record in database.
     *
     * @param  int  $id  model id
     */
    public function delete(int $id): bool
    {
        $result = (new TagDeleteAction)->handle($id);
        CacheHelper::forget([], 'cached_Tags');

        return $result;
    }

    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): Tag
    {
        return CacheHelper::remember([], 
            'cached_tag_' . $id, 31536000,
            fn () => (new TagSearchByIdAction)->handle($id)
        );
    }

    /**
     * Sort record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     * @return \App\Models\Tag
     */
    public function sort($modelId, $newPosition)
    {
        // in x-sort (sortablejs) index starts from 0
        $model = (new TagSortAction)->handle($modelId, $newPosition + 1);

        $this->_clearCaching($model);
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\Tag  $tag  data to update
     */
    private function _clearCaching(Tag $tag): void
    {
        CacheHelper::forget([], 'cached_tags');
        CacheHelper::forget([], 'cached_tag_'.$tag->id);
        CacheHelper::forget([], 'cached_tag'.$tag->slug);
        CacheHelper::forget([], 'form_key_'.$tag->slug);
    }
}
