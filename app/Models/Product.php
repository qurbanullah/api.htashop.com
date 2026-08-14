<?php

namespace App\Models;

use App\Traits\Dam\Damable;
use App\Models\Code;
use App\Models\Organization;
use App\Models\Price;
use App\Models\Revision;
use App\Models\Tenant;
use App\Models\Value;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use Damable, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'organization_id',
        'name',
        'slug',
        'sku',
        'seller_sku',
        'part_number',
        'hs_code',
        'unspsc',
        'ntn',
        'barcode',
        'model_number',
        'status',
        'summary',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class)->orderByDesc('is_default')->orderBy('name');
    }

    public function assignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignable');
    }

    public function codes(): MorphMany
    {
        return $this->morphMany(Code::class, 'codeable');
    }

    public function prices(): MorphMany
    {
        return $this->morphMany(Price::class, 'priceable');
    }

    public function values(): MorphMany
    {
        return $this->morphMany(Value::class, 'valuable');
    }

    public function attributeValues(): MorphMany
    {
        return $this->morphMany(Value::class, 'valuable')
            ->whereHas('definition', function ($query) {
                $query->where('kind', 'attribute')
                    ->whereHas('targets', fn ($targetQuery) => $targetQuery->where('target_type', 'product'));
            });
    }

    public function specificationValues(): MorphMany
    {
        return $this->morphMany(Value::class, 'valuable')
            ->whereHas('definition', function ($query) {
                $query->where('kind', 'specification')
                    ->whereHas('targets', fn ($targetQuery) => $targetQuery->where('target_type', 'product'));
            });
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function features(): MorphToMany
    {
        return $this->morphToMany(Feature::class, 'featureable');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function manufacturers(): MorphToMany
    {
        return $this->morphToMany(Manufacturer::class, 'manufacturable');
    }

    public function brands(): MorphToMany
    {
        return $this->morphToMany(Brand::class, 'brandable');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisable')->orderByDesc('revision_number');
    }

    public function inventories(): MorphMany
    {
        return $this->morphMany(Inventory::class, 'stockable');
    }
}
