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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
class Message extends Model
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
    protected $table = 'messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'message',
        'messageable_type',
        'messageable_id',
    ];

    protected $with = [
        'commenter',
    ];

    /**
     * Get the user for the ticket
     */
    public function commenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the parent messagetable model (ticket).
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }
}
