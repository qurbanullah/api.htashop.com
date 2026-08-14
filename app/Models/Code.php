<?php

namespace App\Models;


use App\Models\Organization;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Code extends Model
{
        use HasFactory;

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'type',
        'value',
        'normalized',
        'context',
        'is_primary',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function codeable(): MorphTo
    {
        return $this->morphTo();
    }
}
