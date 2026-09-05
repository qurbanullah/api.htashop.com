<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Post;

use App\Enums\PostTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy or middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:500'],
            'type' => ['nullable', 'string', Rule::in(PostTypeEnum::values())],
            'metadata' => ['nullable', 'array'],
            'status' => ['required', 'string', Rule::in(['draft', 'scheduled', 'published', 'sent'])],
            'is_published_as_blog' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:50'],
            'recipients' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'is_published_as_blog' => 'publish as blog',
            'scheduled_at' => 'scheduled date',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for the post.',
            'content.required' => 'Please provide content for the post.',
            'status.required' => 'Please select a status.',
            'scheduled_at.after' => 'The scheduled date must be in the future.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge(['status' => 'draft']);
        }

        // Convert is_published_as_blog to boolean
        if ($this->has('is_published_as_blog')) {
            $this->merge([
                'is_published_as_blog' => filter_var($this->is_published_as_blog, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}
