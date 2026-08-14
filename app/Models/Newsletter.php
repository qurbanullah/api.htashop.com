<?php

namespace App\Models;


use App\Traits\Audit\Auditable;
use App\Enums\NewsletterTypeEnum;
use App\Enums\NewsletterStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Models\Tag;

class Newsletter extends Model
{
        use HasFactory, Auditable;

    protected $fillable = [
        'uuid',
        'type',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'images',
        'status',
        'is_published_as_blog',
        'scheduled_at',
        'sent_at',
        'recipients',
        'recipients_count',
        'sent_count',
        'opened_count',
        'clicked_count',
        'metadata',
        'created_by',
        'primary_category_id',
    ];

    protected $casts = [
        'type' => NewsletterTypeEnum::class,
        'status' => NewsletterStatusEnum::class,
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'recipients' => 'array',
        'images' => 'array',
        'metadata' => 'array',
        'is_published_as_blog' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($newsletter) {
            if (empty($newsletter->uuid)) {
                $newsletter->uuid = Str::uuid();
            }
            if (empty($newsletter->type)) {
                $newsletter->type = NewsletterTypeEnum::NEWSLETTER;
            }
            if (empty($newsletter->slug)) {
                $newsletter->slug = Str::slug($newsletter->title);
            }
        });

        static::updating(function ($newsletter) {
            if ($newsletter->isDirty('title') && empty($newsletter->getOriginal('slug'))) {
                $newsletter->slug = Str::slug($newsletter->title);
            }
        });
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function primaryCategory()
    {
        return $this->belongsTo(Category::class, 'primary_category_id');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopePublishedAsBlogs($query)
    {
        return $query->where('is_published_as_blog', true);
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'scheduled')
                    ->where('scheduled_at', '<=', now());
    }

    public function scopeOfType($query, NewsletterTypeEnum|string $type)
    {
        if ($type instanceof NewsletterTypeEnum) {
            return $query->where('type', $type->value);
        }
        return $query->where('type', $type);
    }

    // Accessors & Mutators
    public function getOpenRateAttribute()
    {
        return $this->sent_count > 0 ? round(($this->opened_count / $this->sent_count) * 100, 2) : 0;
    }

    public function getClickRateAttribute()
    {
        return $this->opened_count > 0 ? round(($this->clicked_count / $this->opened_count) * 100, 2) : 0;
    }

    public function getStatusBadgeAttribute()
    {
        return match($this->status) {
            'draft' => ['color' => 'gray', 'text' => 'Draft'],
            'scheduled' => ['color' => 'blue', 'text' => 'Scheduled'],
            'published' => ['color' => 'green', 'text' => 'Published'],
            'sent' => ['color' => 'purple', 'text' => 'Sent'],
            default => ['color' => 'gray', 'text' => 'Unknown'],
        };
    }

    public function getFormattedScheduledAtAttribute()
    {
        return $this->scheduled_at ? $this->scheduled_at->format('M j, Y \a\t g:i A') : null;
    }

    public function getFormattedSentAtAttribute()
    {
        return $this->sent_at ? $this->sent_at->format('M j, Y \a\t g:i A') : null;
    }

    // Helper Methods
    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    public function canBeSent()
    {
        return in_array($this->status, ['draft', 'scheduled']) && !empty($this->content);
    }

    public function canBePublished()
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    public function isOverdue()
    {
        return $this->status === 'scheduled' && $this->scheduled_at && $this->scheduled_at->isPast();
    }

    public function canBeResent()
    {
        return ($this->status === 'scheduled' && $this->scheduled_at && $this->scheduled_at->isPast())
            || ($this->status === 'sending' && $this->updated_at->diffInMinutes(now()) > 60)
            || ($this->status === 'published' && $this->sent_count === 0);
    }

    /**
     * MorphToMany relation to tags (polymorphic)
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /**
     * MorphToMany relation to categories (polymorphic)
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categorizable');
    }
}
