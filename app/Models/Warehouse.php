<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Warehouse extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'tenant_id', 'name', 'code', 'slug',
        'contact_name', 'email', 'phone',
        'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country',
        'is_active', 'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Warehouse $warehouse): void {
            if (empty($warehouse->uuid)) {
                $warehouse->uuid = (string) Str::uuid();
            }
            if (empty($warehouse->slug)) {
                $warehouse->slug = Str::slug($warehouse->name);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function address(): MorphOne
    {
        return $this->morphOne(Address::class, 'addressable')->where('type', 'warehouse');
    }
}
