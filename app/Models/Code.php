<?php

namespace App\Models;

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

    /**
     * @return BelongsTo<Tenant, Code>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Organization, Code>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return MorphTo<Model, Code>
     */
    public function codeable(): MorphTo
    {
        return $this->morphTo();
    }
}
