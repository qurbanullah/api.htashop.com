<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BomLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'bom_request_id',
        'part_name',
        'part_number',
        'specification',
        'quantity',
        'unit',
        'target_unit_price',
        'source_url',
        'notes',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'target_unit_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (BomLine $line): void {
            if (empty($line->uuid)) {
                $line->uuid = (string) Str::uuid();
            }
        });
    }

    public function bomRequest(): BelongsTo
    {
        return $this->belongsTo(BomRequest::class);
    }
}
