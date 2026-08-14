<?php

namespace App\Traits\Translation;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

trait HasTranslations
{
    /**
     * Get all translations for this model.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /**
     * Get translatable fields for this model.
     * Override this method in your model to specify translatable fields.
     *
     * @return array<string>
     */
    public function translatableFields(): array
    {
        return ['name', 'description'];
    }

    /**
     * Get translated value for a specific field.
     *
     * @param string $field Field name to translate
     * @param string|null $locale Language code (null = current locale)
     * @param bool $fallback Whether to fallback to default language
     * @return string|null Translated value or original value
     */
    public function translate(string $field, ?string $locale = null, bool $fallback = true): ?string
    {
        // Use current locale if not specified
        $locale = $locale ?? App::getLocale();

        // Get default language
        $defaultLanguage = Language::getDefault();
        $defaultLocale = $defaultLanguage?->code ?? 'en';

        // If requested locale is default, return original value
        if ($locale === $defaultLocale) {
            return $this->getAttribute($field);
        }

        // Try to get cached translation
        $cacheKey = "translation.{$this->getMorphClass()}.{$this->id}.{$field}.{$locale}";

        $translation = Cache::remember($cacheKey, 3600, function () use ($field, $locale) {
            $language = Language::findByCode($locale);

            if (!$language) {
                return null;
            }

            return $this->translations()
                ->where('language_id', $language->id)
                ->where('field', $field)
                ->first();
        });

        // Return translated value if found
        if ($translation && $translation->value) {
            return $translation->value;
        }

        // Fallback to original value
        if ($fallback) {
            return $this->getAttribute($field);
        }

        return null;
    }

    /**
     * Set translation for a specific field.
     *
     * @param string $field Field name
     * @param string $value Translated value
     * @param string $locale Language code
     * @return Translation|null Created translation
     */
    public function setTranslation(string $field, string $value, string $locale): ?Translation
    {
        $language = Language::findByCode($locale);

        if (!$language) {
            return null;
        }

        // Clear cache
        $cacheKey = "translation.{$this->getMorphClass()}.{$this->id}.{$field}.{$locale}";
        Cache::forget($cacheKey);

        // Update or create translation
        return $this->translations()->updateOrCreate(
            [
                'language_id' => $language->id,
                'field' => $field,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Get all translations for current locale.
     *
     * @param string|null $locale Language code
     * @return array<string, string> Translated fields
     */
    public function getTranslated(?string $locale = null): array
    {
        $locale = $locale ?? App::getLocale();
        $translated = [];

        foreach ($this->translatableFields() as $field) {
            $translated[$field] = $this->translate($field, $locale);
        }

        return $translated;
    }

    /**
     * Check if translation exists for a field.
     *
     * @param string $field Field name
     * @param string $locale Language code
     * @return bool
     */
    public function hasTranslation(string $field, string $locale): bool
    {
        $language = Language::findByCode($locale);

        if (!$language) {
            return false;
        }

        return $this->translations()
            ->where('language_id', $language->id)
            ->where('field', $field)
            ->exists();
    }

    /**
     * Delete all translations for this model.
     */
    public function deleteTranslations(): void
    {
        $this->translations()->delete();

        // Clear all cached translations
        foreach ($this->translatableFields() as $field) {
            $languages = Language::active()->get();
            foreach ($languages as $language) {
                $cacheKey = "translation.{$this->getMorphClass()}.{$this->id}.{$field}.{$language->code}";
                Cache::forget($cacheKey);
            }
        }
    }

    /**
     * Get translation coverage percentage for a locale.
     *
     * @param string $locale Language code
     * @return float Percentage (0-100)
     */
    public function translationCoverage(string $locale): float
    {
        $language = Language::findByCode($locale);

        if (!$language) {
            return 0.0;
        }

        $totalFields = count($this->translatableFields());

        if ($totalFields === 0) {
            return 100.0;
        }

        $translatedCount = $this->translations()
            ->where('language_id', $language->id)
            ->whereIn('field', $this->translatableFields())
            ->count();

        return ($translatedCount / $totalFields) * 100;
    }

    /**
     * Boot the trait.
     */
    protected static function bootHasTranslations(): void
    {
        // Clear cache when model is deleted
        static::deleted(function ($model) {
            $model->deleteTranslations();
        });
    }
}
