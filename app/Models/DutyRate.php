<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Pakistan customs duty & tax rate for an HS code heading/subheading.
 *
 * `hs_code` is matched by longest prefix against a product's `hs_code`, so a
 * row for "8501" also covers "8501.31.00" unless a more specific "8501.31"
 * row exists. Percentages are stored whole (e.g. 10 for 10%).
 */
class DutyRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'hs_code',
        'description',
        'customs_duty',
        'additional_customs_duty',
        'regulatory_duty',
        'sales_tax',
        'china_fta_customs_duty',
        'is_active',
    ];

    protected $casts = [
        'customs_duty' => 'decimal:2',
        'additional_customs_duty' => 'decimal:2',
        'regulatory_duty' => 'decimal:2',
        'sales_tax' => 'decimal:2',
        'china_fta_customs_duty' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
