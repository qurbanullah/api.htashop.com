<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PunchoutInboundOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'buyer_id',
        'payload_id',
        'order_reference',
        'order_id',
        'status',
        'protocol',
        'currency',
        'declared_total',
        'payload',
        'error',
    ];

    protected $casts = [
        'declared_total' => 'decimal:6',
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (PunchoutInboundOrder $inboundOrder): void {
            if (empty($inboundOrder->uuid)) {
                $inboundOrder->uuid = (string) Str::uuid();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(PunchoutBuyer::class, 'buyer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
