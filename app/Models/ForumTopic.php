<?php

namespace App\Models;


use App\Enums\ForumPostStatusEnum;
use App\Traits\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ForumTopic extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'icon',
        'color',
        'sort_order',
        'is_active',
        'is_locked',
        'post_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'sort_order' => 'integer',
            'post_count' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $topic) {
            if (empty($topic->uuid)) {
                $topic->uuid = Str::uuid();
            }
            if (empty($topic->slug)) {
                $topic->slug = Str::slug($topic->name);
                $count = static::withTrashed()->where('slug', $topic->slug)->count();
                if ($count > 0) {
                    $topic->slug .= '-' . ($count + 1);
                }
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'topic_id');
    }

    public function publishedPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'topic_id')
            ->where('status', ForumPostStatusEnum::PUBLISHED->value);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
