<?php

namespace App\Models;

use App\Traits\HasApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasApprovalWorkflow, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'manufacturer_id',
        'name',
        'slug',
        'origin',
        'is_approved',
        'tenant_id',
        'organization_id',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'logo',
        'website',
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
        static::creating(function (Brand $brand): void {
            if (empty($brand->uuid)) {
                $brand->uuid = (string) Str::uuid();
            }
        });
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'brandable');
    }

    public function variants(): MorphToMany
    {
        return $this->morphedByMany(Variant::class, 'brandable');
    }
}
