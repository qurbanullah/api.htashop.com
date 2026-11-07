<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which order line (and how much of it) sits inside one box.
 *
 * A line may span several boxes; the sum across a shipment's boxes may not
 * exceed what the shipment itself carries.
 */
class ParcelItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'parcel_id',
        'order_item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
