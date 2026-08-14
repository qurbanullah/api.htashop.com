<?php

namespace App\Models;


use App\Traits\Dam\Damable;
use App\Models\Contract;
use App\Models\Membership;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
        use Damable, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'type',
        'code',
        'email',
        'phone',
        'website',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (empty($organization->uuid)) {
                $organization->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function buyerContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'buyer_id');
    }

    public function vendorContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'vendor_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }
}
