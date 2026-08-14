<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Value extends Model
{
        use HasFactory;

    protected $fillable = [
        'tenant_id',
        'definition_id',
        'option_id',
        'unit_id',
        'value_text',
        'value_number',
        'value_boolean',
        'value_date',
        'value_datetime',
        'value_json',
        'normalized_number',
        'locale',
        'channel',
        'metadata',
    ];

    protected $casts = [
        'value_number' => 'decimal:6',
        'normalized_number' => 'decimal:6',
        'value_boolean' => 'boolean',
        'value_date' => 'date',
        'value_datetime' => 'datetime',
        'value_json' => 'array',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(Definition::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function valuable(): MorphTo
    {
        return $this->morphTo();
    }
}
