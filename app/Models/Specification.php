<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curated specification key (e.g. "Operating Voltage") that product editors pick
 * from instead of typing a free-form label.
 */
class Specification extends Model
{
    use HasFactory;

    protected $table = 'specifications';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'group',
        'unit',
        'unit_id',
        'description',
        'sort_order',
        'usage_count',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'usage_count' => 'integer',
    ];

    /**
     * Structured unit reference.
     *
     * Deliberately not named `unit()`: that would shadow the legacy `unit`
     * string column and break CSV exports and existing API payloads.
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
