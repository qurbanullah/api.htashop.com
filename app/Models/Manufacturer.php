<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Manufacturer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'code',
        'type',
        'logo',
        'website',
        'country',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
