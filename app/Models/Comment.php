<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'content',
        'is_internal',
        'is_read',
        'attachments',
        'metadata',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_read' => 'boolean',
        'attachments' => 'array',
        'metadata' => 'array',
    ];

    /**
     * Get the commentable entity (Reviewer, Manuscript, etc.)
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the comment author
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for threaded replies)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get child comments (replies)
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at');
    }

    /**
     * Scope for top-level comments only
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope for internal comments
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope for public comments
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Mark comment as read
     */
    public function markAsRead(): bool
    {
        $this->is_read = true;
        return $this->save();
    }

    // ============================================
    // Polymorphic Many-to-Many Context Relations
    // ============================================

    /**
     * Get all context associations for this comment
     */
    public function contexts(): HasMany
    {
        return $this->hasMany(Commentable::class);
    }

    /**
     * Get all revisions this comment is associated with
     */
    public function revisions(): MorphToMany
    {
        return $this->morphedByMany(
            Revision::class,
            'commentable',
            'commentables',
            'comment_id',
            'commentable_id'
        )
            ->wherePivot('context_type', 'revision')
            ->withPivot('metadata')
            ->withTimestamps();
    }

    /**
     * Get all manuscripts this comment is associated with
     */
    public function manuscripts(): MorphToMany
    {
        return $this->morphedByMany(
            Manuscript::class,
            'commentable',
            'commentables',
            'comment_id',
            'commentable_id'
        )
            ->wherePivot('context_type', 'manuscript')
            ->withPivot('metadata')
            ->withTimestamps();
    }

    /**
     * Get all authors this comment is associated with
     */
    public function authors(): MorphToMany
    {
        return $this->morphedByMany(
            User::class,
            'commentable',
            'commentables',
            'comment_id',
            'commentable_id'
        )
            ->wherePivot('context_type', 'author')
            ->withPivot('metadata')
            ->withTimestamps();
    }

    /**
     * Attach a context to this comment
     *
     * @param Model $entity The entity to attach (Revision, Author, etc.)
     * @param string $contextType The type of context (revision, author, section, etc.)
     * @param array $metadata Optional metadata for the context
     * @return Commentable
     */
    public function attachContext($entity, string $contextType = 'revision', array $metadata = []): Commentable
    {
        return Commentable::firstOrCreate([
            'comment_id' => $this->id,
            'commentable_type' => get_class($entity),
            'commentable_id' => $entity->id,
            'context_type' => $contextType,
        ], [
            'metadata' => $metadata,
        ]);
    }

    /**
     * Detach a context from this comment
     *
     * @param Model $entity The entity to detach
     * @param string $contextType The type of context
     * @return int Number of records deleted
     */
    public function detachContext($entity, string $contextType = 'revision'): int
    {
        return Commentable::where([
            'comment_id' => $this->id,
            'commentable_type' => get_class($entity),
            'commentable_id' => $entity->id,
            'context_type' => $contextType,
        ])->delete();
    }

    /**
     * Sync revision contexts
     *
     * @param array $revisionIds Array of revision IDs to sync
     * @return void
     */
    public function syncRevisions(array $revisionIds): void
    {
        // Remove old revision contexts
        $this->contexts()->where('context_type', 'revision')->delete();

        // Add new ones
        foreach ($revisionIds as $revisionId) {
            $revision = Revision::find($revisionId);
            if ($revision) {
                $this->attachContext($revision, 'revision');
            }
        }
    }

    /**
     * Check if comment is associated with a specific revision
     *
     * @param int $revisionId
     * @return bool
     */
    public function hasRevision(int $revisionId): bool
    {
        return $this->revisions()->where('id', $revisionId)->exists();
    }

    /**
     * Get context badges for display
     *
     * @return array
     */
    public function getContextBadges(): array
    {
        return [
            'revisions' => $this->revisions,
            'manuscripts' => $this->manuscripts,
            'authors' => $this->authors,
        ];
    }
}
