<?php

namespace App\Models;

use App\Traits\Audit\Auditable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * The buyer as a party, not as a tenant relationship.
 *
 * Several tenants can sell to the same buyer. They each have a
 * `PunchoutBuyer` row (credentials, contract, settings) that points here, so
 * the buyer's identity and item master are stored once and shared.
 */
class BuyerParty extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'network_id',
        'duns',
        'country',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (BuyerParty $party): void {
            if (empty($party->uuid)) {
                $party->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Identifiers are compared trimmed; an empty value is not an identity.
     */
    protected function networkId(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                $trimmed = trim((string) $value);

                return $trimmed === '' ? null : $trimmed;
            },
        );
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(PunchoutBuyer::class, 'buyer_party_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BuyerItem::class, 'buyer_party_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(BuyerItemMapping::class, 'buyer_party_id');
    }

    public function getAuditInclude(): array
    {
        return ['name', 'network_id', 'duns', 'country'];
    }
}
