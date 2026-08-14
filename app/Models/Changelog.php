<?php

declare(strict_types=1);

/**
 * Changelog Model class file.
 * php version 8.4
 *
 * @category  App\Models
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

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Changelog Model class.
 *
 * @category App\Models
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class Changelog extends Model
{
        use HasFactory;

    /**
     * The database used by the model.
     *
     * @var string
     */
    // protected $connection = 'mariadb';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'content',
        'file_path',
        'file_name',
        'version_number',
        'release_date',
        'is_active',
        'sorting',
        'changelogable_type',
        'changelogable_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'release_date' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent changelogable model (Version, Release, etc.).
     */
    public function changelogable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get formatted markdown content.
     */
    public function getMarkdownContentAttribute(): string
    {
        return \Illuminate\Support\Str::markdown($this->content ?? '');
    }

    /**
     * Scope to get active changelogs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by release date.
     */
    public function scopeByReleaseDate($query, $direction = 'desc')
    {
        return $query->orderBy('release_date', $direction);
    }

    /**
     * Scope to order by sorting.
     */
    public function scopeBySorting($query, $direction = 'asc')
    {
        return $query->orderBy('sorting', $direction);
    }
}
