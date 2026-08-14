<?php

namespace App\Models;


use App\Enums\EulaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Eula extends Model
{
        use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'version',
        'software_id',
        'version_id',
        'title',
        'content',
        'status',
        'effective_date',
        'consent_count',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => EulaStatus::class,
        'effective_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($eula) {
            if (empty($eula->uuid)) {
                $eula->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Relationship: User who created the EULA
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: User who last updated the EULA
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship: Software this EULA applies to
     */
    public function software(): BelongsTo
    {
        return $this->belongsTo(Software::class, 'software_id');
    }

    /**
     * Relationship: Version this EULA applies to
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'version_id');
    }

    /**
     * Relationship: Consents for this EULA
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Scope: Get active EULAs only
     */
    public function scopeActive($query)
    {
        return $query->where('status', EulaStatus::ACTIVE);
    }

    /**
     * Scope: Get EULA for specific software and version
     */
    public function scopeForSoftware($query, ?int $softwareId, ?int $versionId = null)
    {
        $query->where('software_id', $softwareId);

        if ($versionId) {
            $query->where('version_id', $versionId);
        }

        return $query;
    }

    /**
     * Check if this EULA is active
     */
    public function isActive(): bool
    {
        return $this->status === EulaStatus::ACTIVE;
    }

    /**
     * Check if this EULA is draft
     */
    public function isDraft(): bool
    {
        return $this->status === EulaStatus::DRAFT;
    }
}
