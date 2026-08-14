<?php

namespace App\Models;


use App\Enums\ForumPostStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ForumPost extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'slug',
        'user_id',
        'topic_id',
        'title',
        'body',
        'status',
        'is_pinned',
        'is_locked',
        'is_featured',
        'view_count',
        'like_count',
        'comment_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ForumPostStatusEnum::class,
            'is_pinned' => 'boolean',
            'is_locked' => 'boolean',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $post) {
            if (empty($post->uuid)) {
                $post->uuid = Str::uuid();
            }
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
                $count = static::withTrashed()->where('slug', $post->slug)->count();
                if ($count > 0) {
                    $post->slug .= '-' . ($count + 1);
                }
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'topic_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ForumComment::class, 'post_id');
    }

    public function visibleComments(): HasMany
    {
        return $this->hasMany(ForumComment::class, 'post_id')
            ->where('status', 'visible')
            ->whereNull('parent_id');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(ForumLike::class, 'likeable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(ForumReport::class, 'reportable');
    }

    public function scopePublished($query)
    {
        return $query->where('status', ForumPostStatusEnum::PUBLISHED->value);
    }

    public function scopeByTopic($query, int $topicId)
    {
        return $query->where('topic_id', $topicId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('body', 'like', "%{$search}%");
        });
    }

    public function isLikedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    public function refreshCommentCount(): void
    {
        $this->update([
            'comment_count' => $this->comments()->where('status', 'visible')->count(),
        ]);
    }

    public function refreshLikeCount(): void
    {
        $this->update([
            'like_count' => $this->likes()->count(),
        ]);
    }
}
