<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Price extends Model
{
        use HasFactory;

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'contract_id',
        'catalog_id',
        'currency_id',
        'unit_id',
        'type',
        'base_price',
        'sale_price',
        'min_quantity',
        'max_quantity',
        'starts_at',
        'ends_at',
        'priority',
        'is_active',
        'metadata',
        'priceable_type',
        'priceable_id',
    ];

    protected $casts = [
        'base_price' => 'decimal:6',
        'sale_price' => 'decimal:6',
        'min_quantity' => 'decimal:6',
        'max_quantity' => 'decimal:6',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function priceable(): MorphTo
    {
        return $this->morphTo();
    }
}
