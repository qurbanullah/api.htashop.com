<?php

namespace App\Models;


use App\Models\Product;
use App\Models\Variant;
use App\Traits\Translation\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
        use HasFactory, SoftDeletes, HasTranslations;

    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'parent_id',
        'level',
        'path',
        'sort_order',
        'is_active',
        'icon',
        'color',
        'image',
        'metadata'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'sort_order' => 'integer',
        'metadata' => 'array'
    ];

    /**
     * Define which fields can be translated
     */
    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get child categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all ancestors
     */
    public function ancestors()
    {
        $ancestors = collect();
        $category = $this->parent;

        while ($category) {
            $ancestors->prepend($category);
            $category = $category->parent;
        }

        return $ancestors;
    }

    /**
     * Get journals in this category (polymorphic)
     */
    public function journals(): MorphToMany
    {
        return $this->morphedByMany(Journal::class, 'categorizable');
    }

    /**
     * Get manuscripts in this category (polymorphic)
     */
    public function manuscripts(): MorphToMany
    {
        return $this->morphedByMany(Manuscript::class, 'categorizable');
    }

    /**
     * Get tags in this category (polymorphic)
     */
    public function tags(): MorphToMany
    {
        return $this->morphedByMany(Tag::class, 'categorizable');
    }

    public function products(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'categorizable');
    }

    public function highlights(): MorphToMany
    {
        return $this->morphedByMany(Highlight::class, 'categorizable');
    }

    public function variants(): MorphToMany
    {
        return $this->morphedByMany(Variant::class, 'categorizable');
    }

    public function features(): MorphToMany
    {
        return $this->morphedByMany(Feature::class, 'categorizable');
    }

    /**
     * Scope to get only root categories
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get categories by level
     */
    public function scopeByLevel($query, int $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Get the full hierarchical name
     */
    public function getFullNameAttribute(): string
    {
        $ancestors = $this->ancestors();
        $names = $ancestors->pluck('name')->toArray();
        $names[] = $this->name;

        return implode(' → ', $names);
    }

    /**
     * Get the breadcrumb trail
     */
    public function getBreadcrumbAttribute(): array
    {
        $ancestors = $this->ancestors();
        $breadcrumb = $ancestors->toArray();
        $breadcrumb[] = $this->toArray();

        return $breadcrumb;
    }

    /**
     * Check if this category is an ancestor of another category
     */
    public function isAncestorOf(Category $category): bool
    {
        return $category->ancestors()->contains('id', $this->id);
    }

    /**
     * Check if this category is a descendant of another category
     */
    public function isDescendantOf(Category $category): bool
    {
        return $this->ancestors()->contains('id', $category->id);
    }

    /**
     * Update the level and path when parent changes
     */
    protected static function booted()
    {
        static::saving(function ($category) {
            if ($category->parent_id) {
                $parent = $category->parent;
                $category->level = $parent->level + 1;
                $category->path = $parent->path . $category->id . '/';
            } else {
                $category->level = 0;
                $category->path = '/' . $category->id . '/';
            }
        });
    }
}
