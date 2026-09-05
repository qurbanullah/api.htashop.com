<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Post;

use App\Enums\PostTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostUpdateRequest extends FormRequest
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
        // Get the post UUID from the route parameter
        $uuid = $this->route('uuid');

        // Find the post ID by UUID for the unique rule
        $post = \App\Models\Post::where('uuid', $uuid)->first();
        $postId = $post ? $post->id : null;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($postId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'content' => ['sometimes', 'required', 'string'],
            'featured_image' => ['nullable', 'string', 'max:500'],
            'images' => ['nullable', 'array'],
            'images.*' => ['string', 'max:500'],
            'type' => ['nullable', 'string', Rule::in(PostTypeEnum::values())],
            'metadata' => ['nullable', 'array'],
            'status' => ['sometimes', 'required', 'string', Rule::in(['draft', 'scheduled', 'published', 'sent'])],
            'is_published_as_blog' => ['nullable', 'boolean'],
            'scheduled_at' => ['nullable', 'date'],
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
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert is_published_as_blog to boolean if provided
        if ($this->has('is_published_as_blog')) {
            $this->merge([
                'is_published_as_blog' => filter_var($this->is_published_as_blog, FILTER_VALIDATE_BOOLEAN)
            ]);
        }
    }
}
