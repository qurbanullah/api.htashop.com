<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Translation extends Model
{
        /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'language_id',
        'translatable_type',
        'translatable_id',
        'field',
        'value',
    ];

    /**
     * Get the language that owns the translation.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the owning translatable model.
     */
    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter translations by language code.
     */
    public function scopeForLanguage($query, string $languageCode)
    {
        return $query->whereHas('language', function ($q) use ($languageCode) {
            $q->where('code', $languageCode);
        });
    }

    /**
     * Scope to filter translations by field.
     */
    public function scopeForField($query, string $field)
    {
        return $query->where('field', $field);
    }
}
