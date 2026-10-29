<?php

namespace App\Models;

use App\Traits\Audit\Auditable;
use App\Traits\Dam\Damable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use Auditable, Damable, HasFactory, SoftDeletes;

    /**
     * @return array<int, string>
     */
    public function getAuditInclude(): array
    {
        return ['name', 'slug', 'domain', 'is_active', 'settings'];
    }

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'domain',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->uuid)) {
                $tenant->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return HasMany<Organization, Tenant>
     */
    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    /**
     * @return HasMany<Product, Tenant>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<PunchoutSession, Tenant>
     */
    public function punchoutSessions(): HasMany
    {
        return $this->hasMany(PunchoutSession::class);
    }
}
