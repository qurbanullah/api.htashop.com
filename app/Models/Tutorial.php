<?php

namespace App\Models;


use App\Traits\Audit\Auditable;
use App\Traits\Dam\Damable;
use App\Enums\TutorialTypeEnum;
use App\Enums\TutorialStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tutorial extends Model
{
        use HasFactory, Auditable, Damable;

    protected $fillable = [
        'uuid',
        'type',
        'title',
        'slug',
        'excerpt',
        'content',
        'thumbnail',
        'video_file',
        'youtube_url',
        'duration',
        'difficulty_level',
        'status',
        'views_count',
        'likes_count',
        'published_at',
        'metadata',
        'created_by',
        'primary_category_id',
    ];

    protected $casts = [
        'type' => TutorialTypeEnum::class,
        'status' => TutorialStatusEnum::class,
        'published_at' => 'datetime',
        'metadata' => 'array',
        'views_count' => 'integer',
        'likes_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tutorial) {
            if (empty($tutorial->uuid)) {
                $tutorial->uuid = Str::uuid();
            }
            if (empty($tutorial->type)) {
                $tutorial->type = TutorialTypeEnum::VIDEO;
            }
            if (empty($tutorial->slug)) {
                $tutorial->slug = Str::slug($tutorial->title);
            }
        });

        static::updating(function ($tutorial) {
            if ($tutorial->isDirty('title') && empty($tutorial->getOriginal('slug'))) {
                $tutorial->slug = Str::slug($tutorial->title);
            }
        });
    }

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', TutorialStatusEnum::PUBLISHED)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', TutorialStatusEnum::DRAFT);
    }

    public function scopeArchived($query)
    {
        return $query->where('status', TutorialStatusEnum::ARCHIVED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('published_at', 'desc');
    }

    public function scopePopular($query)
    {
        return $query->orderBy('views_count', 'desc');
    }

    // Helper Methods
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function incrementLikes(): void
    {
        $this->increment('likes_count');
    }

    public function isPublished(): bool
    {
        return $this->status === TutorialStatusEnum::PUBLISHED
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function getVideoSource(): ?string
    {
        if ($this->youtube_url) {
            return $this->youtube_url;
        }
        return $this->video_file;
    }

    public function hasVideo(): bool
    {
        return !empty($this->youtube_url) || !empty($this->video_file);
    }

    public function thumbnailAsset(): ?Dam
    {
        return $this->currentDamAsset('thumbnail');
    }

    public function videoFileAsset(): ?Dam
    {
        return $this->currentDamAsset('video_file');
    }

    public function currentDamAsset(string $collection): ?Dam
    {
        if ($this->relationLoaded('dams')) {
            return $this->dams
                ->where('collection_name', $collection)
                ->firstWhere('is_current', true);
        }

        return $this->dams()
            ->where('collection_name', $collection)
            ->where('is_current', true)
            ->latest('id')
            ->first();
    }
}
