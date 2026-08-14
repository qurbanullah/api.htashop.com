<?php

namespace App\Models;


use App\Enums\ForumCommentStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ForumComment extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'user_id',
        'post_id',
        'parent_id',
        'body',
        'status',
        'like_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => ForumCommentStatusEnum::class,
            'like_count' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $comment) {
            if (empty($comment->uuid)) {
                $comment->uuid = Str::uuid();
            }
        });

        static::created(function (self $comment) {
            $comment->post->refreshCommentCount();
            $comment->post->topic->increment('post_count', 0); // touch updated_at
        });

        static::deleted(function (self $comment) {
            if ($comment->post) {
                $comment->post->refreshCommentCount();
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'post_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function visibleReplies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->where('status', ForumCommentStatusEnum::VISIBLE->value);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(ForumLike::class, 'likeable');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(ForumReport::class, 'reportable');
    }

    public function isLikedBy(?User $user): bool
    {
        if (!$user) {
            return false;
        }
        return $this->likes()->where('user_id', $user->id)->exists();
    }

    public function refreshLikeCount(): void
    {
        $this->update([
            'like_count' => $this->likes()->count(),
        ]);
    }
}
