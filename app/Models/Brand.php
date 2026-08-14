<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'manufacturer_id',
        'name',
        'slug',
        'logo',
        'website',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
