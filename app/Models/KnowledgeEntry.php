<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KnowledgeSourceEnum;
use App\Enums\KnowledgeStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A single curated or ingested answer the support assistant may ground on.
 *
 * The knowledge base is global for HTAShop: `tenant_id` is nullable and a
 * null value means the entry applies to everyone. No tenant global scope is
 * applied here on purpose, so global entries are never hidden from a
 * tenant-scoped admin session.
 */
class KnowledgeEntry extends Model
{
    use HasFactory;

    protected $table = 'knowledge_entries';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'locale',
        'title',
        'slug',
        'question',
        'body',
        'source_type',
        'source_id',
        'source_url',
        'tags',
        'status',
        'restricted',
        'priority',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'restricted' => 'boolean',
            'priority' => 'integer',
            'status' => KnowledgeStatusEnum::class,
            'source_type' => KnowledgeSourceEnum::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KnowledgeEntry $entry): void {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }

            if (empty($entry->slug) && ! empty($entry->title)) {
                $entry->slug = Str::slug($entry->title);
            }

            if ($entry->status === KnowledgeStatusEnum::PUBLISHED && empty($entry->published_at)) {
                $entry->published_at = now();
            }
        });

        static::updating(function (KnowledgeEntry $entry): void {
            if (
                $entry->isDirty('status')
                && $entry->status === KnowledgeStatusEnum::PUBLISHED
                && empty($entry->published_at)
            ) {
                $entry->published_at = now();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', KnowledgeStatusEnum::PUBLISHED->value);
    }

    /**
     * Entries matching a locale (including locale-agnostic `*` entries).
     */
    public function scopeForLocale(Builder $query, string $locale): Builder
    {
        return $query->whereIn('locale', [$locale, '*']);
    }

    /**
     * Readable text used for keyword matching and embedding.
     */
    public function searchableText(): string
    {
        return trim(implode("\n", array_filter([
            $this->title,
            $this->question,
            $this->body,
        ])));
    }
}
