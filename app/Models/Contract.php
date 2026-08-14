<?php

namespace App\Models;


use App\Models\Catalog;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Price;
use App\Enums\ContractTypeEnum;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contract extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'vendor_id',
        'buyer_id',
        'catalog_id',
        'currency_id',
        'name',
        'code',
        'contract_type',
        'status',
        'starts_at',
        'ends_at',
        'metadata',
    ];

    protected $casts = [
        'contract_type' => ContractTypeEnum::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Contract $contract): void {
            if (empty($contract->uuid)) {
                $contract->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'vendor_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'buyer_id');
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
