<?php

namespace App\Http\Resources\V1\Feature;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get the locale from Accept-Language header or app locale
        $locale = $request->header('Accept-Language') ?? app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->translate('name', $locale),
            'slug' => $this->slug,
            'icon' => $this->icon,
            'type' => $this->type,
            'description' => $this->translate('description', $locale),
            'usage_count' => $this->usage_count,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Translation metadata
            '_locale' => $locale,
            '_available_locales' => Language::active()->pluck('code')->toArray(),
            '_translation_coverage' => [
                'name' => $this->translationCoverage('name'),
                'description' => $this->translationCoverage('description'),
            ],
        ];
    }
}
