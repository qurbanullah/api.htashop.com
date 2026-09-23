<?php

declare(strict_types=1);

/**
 * Post CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Post
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

namespace App\Actions\Post;

use App\Models\Post;
use App\Models\Tag;
use App\Models\Category;
use App\Traits\Post\GeneratesUniqueSlug;
use App\Enums\PostTypeEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRUD operation class for Post Model.
 *
 * @category App\Actions\Post
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class CreatePostAction
{
    use GeneratesUniqueSlug;

    /**
     * Validate and create post record in database.
     *
     * @param  array  $data  post values
     */
    public function handle(array $data): Post
    {
        return DB::transaction(
            function () use ($data) {
                // Create the post record
                $post = tap(
                    Post::create(
                        [
                            'uuid' => data_get($data, 'uuid', Str::uuid()),
                            'title' => data_get($data, 'title'),
                            // Always auto-generate slug from title for consistency
                            'slug' => $this->generateUniqueSlug(data_get($data, 'title')),
                            'type' => data_get($data, 'type', PostTypeEnum::BLOG->value),
                            'excerpt' => data_get($data, 'excerpt'),
                            'content' => data_get($data, 'content'),
                            'featured_image' => data_get($data, 'featured_image'),
                            'images' => data_get($data, 'images', []),
                            'status' => data_get($data, 'status', 'draft'),
                            'is_published_as_blog' => data_get($data, 'is_published_as_blog', false),
                            'scheduled_at' => data_get($data, 'scheduled_at'),
                            'sent_at' => data_get($data, 'sent_at'),
                            'created_by' => data_get($data, 'created_by'),
                            'sent_count' => data_get($data, 'sent_count', 0),
                            'opened_count' => data_get($data, 'opened_count', 0),
                            'clicked_count' => data_get($data, 'clicked_count', 0),
                            'recipients' => data_get($data, 'recipients', []),
                            'primary_category_id' => data_get($data, 'primary_category_id'),
                            'metadata' => data_get($data, 'metadata', []),
                            // Don't store tags and categories in JSON columns - use polymorphic relations
                        ]
                    )
                )->target;

                // Sync tag relations (Tag model & taggables polymorphic pivot)
                $tags = data_get($data, 'tags', []);
                if (is_array($tags) && count($tags) > 0) {
                    $tagIds = [];
                    foreach ($tags as $t) {
                        $name = trim((string) $t);
                        if ($name === '') {
                            continue;
                        }
                        $tag = Tag::firstOrCreate(
                            ['name' => $name],
                            ['slug' => Str::slug($name)]
                        );
                        $tagIds[] = $tag->id;
                    }
                    if (!empty($tagIds)) {
                        $post->tags()->sync($tagIds);
                    }
                }

                // Sync category relations (Category model & categorizables polymorphic pivot)
                $categories = data_get($data, 'categories', []);
                if (is_array($categories) && count($categories) > 0) {
                    $categoryIds = [];
                    foreach ($categories as $c) {
                        $name = trim((string) $c);
                        if ($name === '') {
                            continue;
                        }
                        $category = Category::firstOrCreate(
                            ['name' => $name],
                            ['slug' => Str::slug($name), 'is_active' => true]
                        );
                        $categoryIds[] = $category->id;
                    }
                    if (!empty($categoryIds)) {
                        $post->categories()->sync($categoryIds);

                        // Auto-set primary_category_id to first category if not provided
                        if (empty($data['primary_category_id']) && !empty($categoryIds)) {
                            $post->primary_category_id = $categoryIds[0];
                            $post->save();
                        }
                    }
                }

                return $post;
            }
        );
    }
}
