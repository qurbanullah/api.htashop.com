<?php

declare(strict_types=1);

/**
 * Newsletter CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Newsletter
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

namespace App\Actions\Newsletter;

use App\Models\Newsletter;
use App\Models\Tag;
use App\Models\Category;
use App\Traits\Newsletter\GeneratesUniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * CRUD operation class for Newsletter Model.
 *
 * @category App\Actions\Newsletter
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class UpdateNewsletterAction
{
    use GeneratesUniqueSlug;

    /**
     * Validate and update newsletter record in database.
     *
     * @param  Newsletter  $newsletter  newsletter model instance
     * @param  array  $data  newsletter values
     */
    public function handle(Newsletter $newsletter, array $data): Newsletter
    {
        return DB::transaction(
            function () use ($newsletter, $data) {
                // Determine slug: regenerate only if title changed, otherwise keep existing
                $newSlug = $newsletter->slug;
                if ($this->shouldRegenerateSlug($newsletter, $data)) {
                    $newSlug = $this->generateUniqueSlug(data_get($data, 'title'), $newsletter->id);
                }

                $newsletter->update([
                    'title' => data_get($data, 'title', $newsletter->title),
                    // Slug is auto-managed - frontend cannot change it directly
                    'slug' => $newSlug,
                    'type' => data_get($data, 'type', $newsletter->type),
                    'excerpt' => data_get($data, 'excerpt', $newsletter->excerpt),
                    'content' => data_get($data, 'content', $newsletter->content),
                    'featured_image' => data_get($data, 'featured_image', $newsletter->featured_image),
                    'images' => data_get($data, 'images', $newsletter->images ?? []),
                    'status' => data_get($data, 'status', $newsletter->status),
                    'is_published_as_blog' => data_get($data, 'is_published_as_blog', $newsletter->is_published_as_blog),
                    'scheduled_at' => data_get($data, 'scheduled_at', $newsletter->scheduled_at),
                    'sent_at' => data_get($data, 'sent_at', $newsletter->sent_at),
                    'sent_count' => data_get($data, 'sent_count', $newsletter->sent_count),
                    'opened_count' => data_get($data, 'opened_count', $newsletter->opened_count),
                    'clicked_count' => data_get($data, 'clicked_count', $newsletter->clicked_count),
                    'recipients' => data_get($data, 'recipients', $newsletter->recipients ?? []),
                    'primary_category_id' => data_get($data, 'primary_category_id', $newsletter->primary_category_id),
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
                    $newsletter->tags()->sync($tagIds);
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
                    $newsletter->categories()->sync($categoryIds);

                    // Auto-set primary_category_id to first category if not provided
                    if (empty($data['primary_category_id']) && !empty($categoryIds)) {
                        $newsletter->primary_category_id = $categoryIds[0];
                        $newsletter->save();
                    }
                }

                return $newsletter->fresh();
            }
        );
    }
}
