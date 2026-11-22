<?php

namespace App\Models;

use App\Traits\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One of the buyer's own item numbers, shared by every tenant selling to that
 * buyer.
 *
 * Buyers publish a short and a long description; both are kept. The code is
 * stored as supplied (case and leading zeros matter to the buyer) with a
 * normalised `code_key` for lookups.
 */
class BuyerItem extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'uuid',
        'buyer_party_id',
        'code',
        'code_key',
        'short_description',
        'long_description',
        'unit_of_measure',
        'manufacturer_part_number',
        'gtin',
        'unspsc',
        'is_active',
        'source',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (BuyerItem $item): void {
            if (empty($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (BuyerItem $item): void {
            $item->code_key = self::keyFor((string) $item->code);
        });
    }

    /**
     * Case-insensitive comparison key. Surrounding whitespace is stripped for
     * the same reason as BuyerCookie: it is transport noise, not the value.
     */
    public static function keyFor(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(BuyerParty::class, 'buyer_party_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(BuyerItemMapping::class);
    }

    /**
     * The description to put on a cart line or order line: the short one,
     * falling back to the long one.
     */
    public function displayDescription(): ?string
    {
        $short = trim((string) $this->short_description);

        if ($short !== '') {
            return $short;
        }

        $long = trim((string) $this->long_description);

        return $long === '' ? null : $long;
    }

    public function getAuditInclude(): array
    {
        return [
            'buyer_party_id',
            'code',
            'short_description',
            'long_description',
            'unit_of_measure',
            'manufacturer_part_number',
            'gtin',
            'unspsc',
            'is_active',
        ];
    }
}
