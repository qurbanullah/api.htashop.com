<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Commentable Pivot Model
 *
 * Represents the polymorphic many-to-many relationship between comments
 * and various entities (Revisions, Authors, Sections, etc.)
 *
 * This allows comments to have multiple contexts:
 * - A comment belongs to a Reviewer (primary owner)
 * - The same comment can relate to multiple Revisions (context)
 * - Can also relate to Authors, Sections, Figures, etc.
 */
class Commentable extends Model
{
        protected $fillable = [
        'comment_id',
        'commentable_type',
        'commentable_id',
        'context_type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the comment this pivot belongs to
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    /**
     * Get the owning commentable model (Revision, Author, etc.)
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by context type
     */
    public function scopeOfType($query, string $contextType)
    {
        return $query->where('context_type', $contextType);
    }

    /**
     * Scope to get revision contexts only
     */
    public function scopeRevisions($query)
    {
        return $query->where('context_type', 'revision');
    }

    /**
     * Scope to get author contexts only
     */
    public function scopeAuthors($query)
    {
        return $query->where('context_type', 'author');
    }
}
