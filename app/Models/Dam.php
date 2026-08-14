<?php

namespace App\Models;


use App\Models\DamCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Dam extends Model
{
        use HasFactory, SoftDeletes;

    protected $table = 'dams';

    protected $fillable = [
        'uuid', 'damable_type', 'damable_id', 'collection_name',
        'sort_order',
        'file_name', 'disk', 'bucket', 'object_key',
        'mime_type', 'size', 'storage_class', 'checksum_sha256', 'etag',
        'custom_properties', 'metadata', 'origin_url', 'uploaded_by',
        'derived_from_id', 'version', 'is_current'
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'custom_properties' => 'array',
        'metadata' => 'array',
        'is_current' => 'boolean',
    ];

    // Polymorphic relation to owner model
    public function damable()
    {
        return $this->morphTo();
    }

    // Derivatives / versions
    public function derivatives()
    {
        return $this->hasMany(self::class, 'derived_from_id');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(DamCollection::class, 'dam_collection_items')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('dam_collections.kind')
            ->orderBy('dam_collections.name');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'derived_from_id');
    }

    // Scope to find by checksum
    public function scopeFindByChecksum($query, $sha256)
    {
        return $query->where('checksum_sha256', $sha256);
    }

    // Helper to get a public or signed URL; adapter implementation left to storage layer
    public function getUrl(array $options = []): ?string
    {
        if (! $this->disk || ! $this->object_key) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->url($this->object_key);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasCollectionKey(string $key): bool
    {
        if ($this->relationLoaded('collections')) {
            return $this->collections->contains(fn (DamCollection $collection) => $collection->key === $key);
        }

        return $this->collections()->where('key', $key)->exists();
    }
}
