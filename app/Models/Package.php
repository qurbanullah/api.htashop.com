<?php

declare(strict_types=1);

/**User
 * Model class file.
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Project Model class.
 *
 * @category App\Models
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class Package extends Model
{
        use HasFactory;

    /**
     * The database used by the model.
     *
     * @var string
     */
    // protected $connection = 'mariadb';

    public function __construct(array $attributes = [])
    {
        // Use default connection in testing environment
        if (app()->environment('testing')) {
            $this->connection = config('database.default');
        }

        parent::__construct($attributes);
    }

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'packages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'slug',
        'summary',
        'description',
        'image',
        'is_active',
        'sorting',
    ];

    /**
     * Mutator to set the sorting position.
     */
    public function setSortingAttribute($value)
    {
        // If no value is provided, set it to the next available position
        if (empty($value)) {
            $this->attributes['sorting'] = $this->getNextSortingPosition();
        } else {
            $this->attributes['sorting'] = $value;
        }
    }

    /**
     * Get the next available sorting position.
     */
    protected function getNextSortingPosition()
    {
        // Get the maximum sorting position from the database
        $maxPosition = static::max('sorting');

        // Return the next position (max + 1)
        return $maxPosition + 1;
    }

    /**
     * Get all of the features that are assigned this packages.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function features() : MorphToMany
    {
        return $this->morphToMany(Feature::class, 'featureable');
    }

    /**
     * Get all tickets that are related to this package.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tickets(): MorphToMany
    {
        return $this->morphedByMany(Ticket::class, 'packageable');
    }
}
