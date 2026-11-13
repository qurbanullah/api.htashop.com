<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteResponseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_response_id',
        'product_id',
        'variant_id',
        'name',
        'sku',
        'part_number',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'line_total',
        'lead_time_days',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'line_total' => 'decimal:2',
        'lead_time_days' => 'integer',
        'sort_order' => 'integer',
    ];

    public function response(): BelongsTo
    {
        return $this->belongsTo(QuoteResponse::class, 'quote_response_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }
}
