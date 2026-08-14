<?php

namespace App\Http\Requests\V1\Tutorial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TutorialTypeEnum;
use App\Enums\TutorialStatusEnum;

class TutorialStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by Policy
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'type' => ['required', 'string', Rule::in(array_column(TutorialTypeEnum::cases(), 'value'))],
            'excerpt' => 'nullable|string|max:500',
            'content' => 'nullable|string',
            'thumbnail' => 'nullable|string',
            'video_file' => 'nullable|string',
            'youtube_url' => 'nullable|url|max:500',
            'duration' => 'nullable|integer|min:0',
            'difficulty_level' => ['nullable', 'string', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'status' => ['required', 'string', Rule::in(array_column(TutorialStatusEnum::cases(), 'value'))],
            'published_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string',
            'categories' => 'nullable|array',
            'categories.*' => 'string',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Tutorial title is required',
            'title.max' => 'Tutorial title must not exceed 255 characters',
            'type.required' => 'Tutorial type is required',
            'type.in' => 'Invalid tutorial type selected',
            'youtube_url.url' => 'YouTube URL must be a valid URL',
            'duration.integer' => 'Duration must be a number',
            'difficulty_level.in' => 'Difficulty level must be beginner, intermediate, or advanced',
        ];
    }
}
