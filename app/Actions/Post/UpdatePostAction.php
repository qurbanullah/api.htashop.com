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
use Illuminate\Support\Facades\DB;

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
class UpdatePostAction
{
    use GeneratesUniqueSlug;

    /**
     * Validate and update post record in database.
     *
     * @param  Post  $post  post model instance
     * @param  array  $data  post values
     */
    public function handle(Post $post, array $data): Post
    {
        return DB::transaction(
            function () use ($post, $data) {
                // Determine slug: regenerate only if title changed, otherwise keep existing
                $newSlug = $post->slug;
                if ($this->shouldRegenerateSlug($post, $data)) {
                    $newSlug = $this->generateUniqueSlug(data_get($data, 'title'), $post->id);
                }

                $post->update([
                    'title' => data_get($data, 'title', $post->title),
                    // Slug is auto-managed - frontend cannot change it directly
                    'slug' => $newSlug,
                    'type' => data_get($data, 'type', $post->type),
                    'excerpt' => data_get($data, 'excerpt', $post->excerpt),
                    'content' => data_get($data, 'content', $post->content),
                    'featured_image' => data_get($data, 'featured_image', $post->featured_image),
                    'images' => data_get($data, 'images', $post->images ?? []),
                    'status' => data_get($data, 'status', $post->status),
                    'is_published_as_blog' => data_get($data, 'is_published_as_blog', $post->is_published_as_blog),
                    'scheduled_at' => data_get($data, 'scheduled_at', $post->scheduled_at),
                    'sent_at' => data_get($data, 'sent_at', $post->sent_at),
                    'sent_count' => data_get($data, 'sent_count', $post->sent_count),
                    'opened_count' => data_get($data, 'opened_count', $post->opened_count),
                    'clicked_count' => data_get($data, 'clicked_count', $post->clicked_count),
                    'recipients' => data_get($data, 'recipients', $post->recipients ?? []),
                    'primary_category_id' => data_get($data, 'primary_category_id', $post->primary_category_id),
                    'metadata' => array_key_exists('metadata', $data)
                        ? (data_get($data, 'metadata') ?? [])
                        : ($post->metadata ?? []),
                    // Don't update tags and categories in JSON columns - use polymorphic relations instead
                ]);

                // Sync Tag polymorphic relations if tags provided
                $tags = data_get($data, 'tags', []);
                if (is_array($tags)) {
                    $tagIds = [];
                    foreach ($tags as $t) {
                        $name = trim((string) $t);
                        if ($name === '') {
                            continue;
                        }
                        $tag = Tag::firstOrCreate(
                            ['name' => $name],
                            ['slug' => \Illuminate\Support\Str::slug($name)]
                        );
                        $tagIds[] = $tag->id;
                    }
                    $post->tags()->sync($tagIds);
                }

                // Sync Category polymorphic relations if categories provided
                $categories = data_get($data, 'categories', []);
                if (is_array($categories)) {
                    $categoryIds = [];
                    foreach ($categories as $c) {
                        $name = trim((string) $c);
                        if ($name === '') {
                            continue;
                        }
                        $category = Category::firstOrCreate(
                            ['name' => $name],
                            ['slug' => \Illuminate\Support\Str::slug($name), 'is_active' => true]
                        );
                        $categoryIds[] = $category->id;
                    }
                    $post->categories()->sync($categoryIds);

                    // Auto-set primary_category_id to first category if not provided
                    if (empty($data['primary_category_id']) && !empty($categoryIds)) {
                        $post->primary_category_id = $categoryIds[0];
                        $post->save();
                    }
                }

                return $post->fresh();
            }
        );
    }
}
