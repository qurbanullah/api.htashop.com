<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Manufacturer extends Model
{
    use HasApprovalWorkflow, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'code',
        'type',
        'origin',
        'is_approved',
        'tenant_id',
        'organization_id',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'logo',
        'website',
        'country',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Manufacturer $manufacturer): void {
            if (empty($manufacturer->uuid)) {
                $manufacturer->uuid = (string) Str::uuid();
            }
        });
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class)->orderBy('name');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'manufacturable');
    }

    public function variants(): MorphToMany
    {
        return $this->morphedByMany(Variant::class, 'manufacturable');
    }
}
