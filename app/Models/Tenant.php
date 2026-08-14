<?php

namespace App\Models;

use App\Traits\Dam\Damable;
use App\Models\Organization;
use App\Models\PunchoutSession;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use Damable, HasFactory, SoftDeletes;

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

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function punchoutSessions(): HasMany
    {
        return $this->hasMany(PunchoutSession::class);
    }
}
