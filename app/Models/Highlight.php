<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Highlight extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'tenant_id',
        'label',
        'heading',
        'body',
        'code',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Highlight $highlight): void {
            if (empty($highlight->uuid)) {
                $highlight->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'highlightable');
    }

    public function variants(): MorphToMany
    {
        return $this->morphedByMany(Variant::class, 'highlightable');
    }
}
