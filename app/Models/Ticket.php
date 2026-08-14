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


use App\Enums\PriorityEnum;
use App\Enums\ReproducibilityEnum;
use App\Enums\SeverityEnum;
use App\Enums\StatusEnum;
use App\Enums\StypeEnum;
use App\Traits\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
class Ticket extends Model
{
        use HasFactory, Auditable;

    /**
     * The database used by the model.
     *
     * @var string
     */
    // protected $connection = 'mariadb';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'tickets';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'guest_name',
        'guest_email',
        'title',
        'slug',
        'stype',
        'severity',
        'reproducibility',
        'priority',
        'status',
        'is_visible',
        'is_resolved',
        'is_locked',
        'is_archived',
        'description',
        'steps_to_reproduce',
        'additional_information',
        'resolved_on',
        'archived_on',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_locked' => 'boolean',
            'is_archived' => 'boolean',
            'resolved_on' => 'datetime',
            'archived_on' => 'datetime',
            'status' => StatusEnum::class,
            'severity' => SeverityEnum::class,
            'reproducibility' => ReproducibilityEnum::class,
            'priority' => PriorityEnum::class,
            'stype' => StypeEnum::class,
        ];
    }

    /**
     * Boot the model and set default attributes.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (empty($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }

            if (empty($ticket->slug) && ! empty($ticket->title)) {
                $ticket->slug = Str::slug($ticket->title);
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

    /**
     * Get the user for the ticket
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the best available submitter name for this ticket.
     */
    public function getSubmitterNameAttribute(): string
    {
        return $this->reporter?->name
            ?? $this->guest_name
            ?? 'Guest';
    }

    /**
     * Get the best available submitter email for this ticket.
     */
    public function getSubmitterEmailAttribute(): ?string
    {
        return $this->reporter?->email
            ?? $this->guest_email;
    }

    /**
     * Get the user for the ticket including soft deleted users
     */
    public function reporterWithTrashed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Get the user for the license
     */
    public function stypes(): MorphToMany
    {
        return $this->morphToMany(Stype::class, 'stypeable');
    }

    /**
     * Get the user for the license
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * Get the user for the license
     */
    public function labels(): MorphToMany
    {
        return $this->morphToMany(Lable::class, 'labelable');
    }

    /**
     * Get all software related to this ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function softwares(): MorphToMany
    {
        return $this->morphToMany(Software::class, 'softwareable');
    }

    /**
     * Get all versions related to this ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function versions(): MorphToMany
    {
        return $this->morphToMany(Version::class, 'versionable');
    }

    /**
     * Get all license types related to this ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function ltypes(): MorphToMany
    {
        return $this->morphToMany(Ltype::class, 'ltypeable');
    }

    /**
     * Get all packages related to this ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function packages(): MorphToMany
    {
        return $this->morphToMany(Package::class, 'packageable');
    }

    /**
     * Get all of the ticket's messages.
     */
    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    /**
     * Get all of the ticket's assignments.
     */
    public function assignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignable');
    }

    /**
     * Get all active assignments for the ticket.
     */
    public function activeAssignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignable')->where('is_active', true);
    }

    /**
     * Check if the ticket is currently assigned to someone.
     *
     * @return bool
     */
    public function isAssigned(): bool
    {
        return $this->assignments()->where('is_active', true)->exists();
    }

    /**
     * Get the current active assignment for the ticket.
     *
     * @return \App\Models\Assignment|null
     */
    public function currentAssignment()
    {
        return $this->assignments()->where('is_active', true)->latest('assigned_at')->first();
    }

    /**
     * Get the user currently assigned to this ticket.
     *
     * @return \App\Models\User|null
     */
    public function assignedUser()
    {
        $assignment = $this->currentAssignment();
        return $assignment ? $assignment->assignedTo : null;
    }

    /**
     * Get the user currently assigned to this ticket (including soft deleted).
     *
     * @return \App\Models\User|null
     */
    public function assignedUserWithTrashed()
    {
        $assignment = $this->currentAssignment();
        return $assignment ? User::withTrashed()->find($assignment->assigned_to) : null;
    }

    /**
     * Get the formatted ticket number.
     *
     * @return string
     */
    public function getTicketNumberAttribute(): string
    {
        return str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }
}
