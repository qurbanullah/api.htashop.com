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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

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
class Label extends Model
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
    protected $table = 'labels';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'summary',
        'description',
        'image',
        'is_active',
        'sorting',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Label $label): void {
            if (empty($label->uuid)) {
                $label->uuid = (string) Str::uuid();
            }
        });
    }

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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSystem(): bool
    {
        return is_null($this->tenant_id);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
    }

    public function scopeSystem($query)
    {
        return $query->whereNull('tenant_id');
    }

    public function taxonomyLevelNames(): array
    {
        return $this->metadata['taxonomy_levels'] ?? [];
    }

    public function definitions(): MorphToMany
    {
        return $this->morphedByMany(Definition::class, 'labelable')->withTimestamps();
    }

    /**
     * Get all of the tickets that are assigned this label.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tickets() : MorphToMany
    {
        return $this->morphedByMany(Ticket::class, 'labelable');
    }
}
