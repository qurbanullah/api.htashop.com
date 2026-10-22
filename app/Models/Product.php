<?php

namespace App\Models;

use App\Enums\SalesChannel as SalesChannelCode;
use App\Enums\Sourcing;
use App\Traits\Audit\Auditable;
use App\Traits\Dam\Damable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use Auditable, Damable, HasFactory, SoftDeletes;

    /**
     * Audit the commercial identity and availability fields, not the large
     * description/metadata blobs.
     */
    public function getAuditInclude(): array
    {
        return [
            'name',
            'slug',
            'sku',
            'status',
            'is_active',
            'sourcing',
            'lead_time_days',
            'origin_country',
            'part_number',
            'barcode',
            'model_number',
        ];
    }

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
        'sourcing',
        'lead_time_days',
        'origin_country',
        'sourcing_url',
        'supplier_reference',
        'status',
        'summary',
        'description',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'lead_time_days' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * New products are ordinary stock unless told otherwise. Eloquent applies
     * this default on new instances, so the in-memory model and the database
     * column default always agree (the column default alone would leave a
     * freshly-created model's `sourcing` null until reloaded).
     */
    protected $attributes = [
        'sourcing' => Sourcing::IN_STOCK,
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

    public function salesChannels(): BelongsToMany
    {
        return $this->belongsToMany(SalesChannel::class, 'product_sales_channel');
    }

    /**
     * Scope to the channels a product is published in.
     *
     * A product with no channel memberships is treated as storefront-only so
     * rows created before the channel rollout do not disappear from the B2C
     * catalog. Punchout always requires an explicit membership.
     */
    public function scopeForChannel(Builder $query, string|array|null $channel): Builder
    {
        $channels = is_array($channel) ? $channel : [$channel];
        $channels = array_values(array_filter($channels));

        if ($channels === []) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($channels): void {
            $inner->whereHas(
                'salesChannels',
                fn (Builder $relation) => $relation->whereIn('code', $channels),
            );

            if (in_array(SalesChannelCode::STOREFRONT, $channels, true)) {
                $inner->orWhereDoesntHave('salesChannels');
            }
        });
    }

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->when($tenantId, fn (Builder $inner) => $inner->where('tenant_id', $tenantId));
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

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function highlights(): MorphToMany
    {
        return $this->morphToMany(Highlight::class, 'highlightable')
            ->withPivot(['sort_order', 'heading_override', 'body_override'])
            ->orderByPivot('sort_order');
    }

    /**
     * Whether this product is ordered from a supplier only after the customer
     * pays, rather than being picked from held stock.
     */
    public function requiresAdvancePayment(): bool
    {
        return Sourcing::requiresAdvancePayment((string) ($this->sourcing ?: Sourcing::IN_STOCK));
    }

    /**
     * A customer-facing availability label derived from the sourcing mode and
     * lead time, e.g. "In Stock" or "Ships in 18 days".
     */
    public function availabilityLabel(): string
    {
        $sourcing = $this->sourcing ?: Sourcing::IN_STOCK;
        $lead = $this->lead_time_days;

        if ($sourcing === Sourcing::IN_STOCK) {
            return 'In Stock';
        }

        return match ($sourcing) {
            Sourcing::ON_DEMAND => $lead ? "Ships in {$lead} days" : 'Import on Demand',
            Sourcing::DROPSHIP => $lead ? "Ships in {$lead} days" : 'Drop-ship',
            Sourcing::PREORDER => $lead ? "Pre-order · ships in {$lead} days" : 'Pre-order',
            default => Sourcing::label($sourcing),
        };
    }
}
