<?php

namespace App\Http\Middleware;

use App\Models\Language;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->getPreferredLocale($request);

        // Set the application locale
        App::setLocale($locale);

        // Store locale in session for persistence
        Session::put('locale', $locale);

        return $next($request);
    }

    /**
     * Determine the preferred locale from multiple sources.
     *
     * Priority order:
     * 1. Query parameter (?lang=ur)
     * 2. Accept-Language header
     * 3. Session
     * 4. Default language from database
     * 5. Fallback to 'en'
     *
     * @param Request $request
     * @return string Language code
     */
    private function getPreferredLocale(Request $request): string
    {
        if (!$this->hasLanguagesTable()) {
            return config('app.locale', 'en');
        }

        // 1. Check query parameter
        if ($request->has('lang')) {
            $queryLang = $request->get('lang');
            if ($this->isValidLocale($queryLang)) {
                return $queryLang;
            }
        }

        // 2. Check Accept-Language header
        $headerLocale = $request->header('Accept-Language');
        if ($headerLocale) {
            $locale = $this->parseAcceptLanguageHeader($headerLocale);
            if ($locale && $this->isValidLocale($locale)) {
                return $locale;
            }
        }

        // 3. Check session
        if (Session::has('locale')) {
            $sessionLocale = Session::get('locale');
            if ($this->isValidLocale($sessionLocale)) {
                return $sessionLocale;
            }
        }

        // 4. Get default language from database
        $defaultLanguage = Language::getDefault();
        if ($defaultLanguage) {
            return $defaultLanguage->code;
        }

        // 5. Fallback to config or 'en'
        return config('app.locale', 'en');
    }

    /**
     * Parse Accept-Language header to extract primary language code.
     *
     * Example: "en-US,en;q=0.9,ur;q=0.8" -> "en"
     *
     * @param string $header
     * @return string|null
     */
    private function parseAcceptLanguageHeader(string $header): ?string
    {
        // Split by comma to get all languages
        $languages = explode(',', $header);

        foreach ($languages as $lang) {
            // Remove quality factor (;q=0.9)
            $lang = explode(';', $lang)[0];
            $lang = trim($lang);

            // Extract primary language code (en-US -> en)
            if (strpos($lang, '-') !== false) {
                $lang = explode('-', $lang)[0];
            }

            // Check if this language is supported
            if ($this->isValidLocale($lang)) {
                return $lang;
            }
        }

        return null;
    }

    /**
     * Check if locale is valid and active.
     *
     * @param string $locale
     * @return bool
     */
    private function isValidLocale(string $locale): bool
    {
        if (!$this->hasLanguagesTable()) {
            return in_array($locale, [config('app.locale', 'en'), config('app.fallback_locale', 'en')], true);
        }

        // Cache the valid locales for performance
        $cacheKey = 'valid_locales';

        $validLocales = cache()->remember($cacheKey, 3600, function () {
            return Language::active()->pluck('code')->toArray();
        });

        return in_array($locale, $validLocales);
    }

    /**
     * Determine whether the languages table is available.
     */
    private function hasLanguagesTable(): bool
    {
        static $hasLanguagesTable;

        if ($hasLanguagesTable !== null) {
            return $hasLanguagesTable;
        }

        try {
            $hasLanguagesTable = Schema::hasTable('languages');
        } catch (\Throwable) {
            $hasLanguagesTable = false;
        }

        return $hasLanguagesTable;
    }
}
