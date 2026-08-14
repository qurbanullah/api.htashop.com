<?php

namespace App\Models;

use App\Traits\Dam\Damable;
use App\Models\Code;
use App\Models\Price;
use App\Models\Revision;
use App\Models\Value;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Variant extends Model
{
    use Damable, HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'product_id',
        'name',
        'slug',
        'sku',
        'seller_sku',
        'status',
        'summary',
        'description',
        'configuration',
        'is_default',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'configuration' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Variant $variant): void {
            if (empty($variant->uuid)) {
                $variant->uuid = (string) Str::uuid();
            }

            if (empty($variant->tenant_id) && ! empty($variant->product_id)) {
                $variant->tenant_id = Product::query()
                    ->whereKey($variant->product_id)
                    ->value('tenant_id');
            }
        });

        static::updating(function (Variant $variant): void {
            if ($variant->isDirty('product_id') && ! empty($variant->product_id)) {
                $variant->tenant_id = Product::query()
                    ->whereKey($variant->product_id)
                    ->value('tenant_id');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
                    ->whereHas('targets', fn ($targetQuery) => $targetQuery->where('target_type', 'variant'));
            });
    }

    public function specificationValues(): MorphMany
    {
        return $this->morphMany(Value::class, 'valuable')
            ->whereHas('definition', function ($query) {
                $query->where('kind', 'specification')
                    ->whereHas('targets', fn ($targetQuery) => $targetQuery->where('target_type', 'variant'));
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

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisable')->orderByDesc('revision_number');
    }

    public function inventories(): MorphMany
    {
        return $this->morphMany(Inventory::class, 'stockable');
    }
}
