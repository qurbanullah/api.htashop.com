<?php

declare(strict_types=1);

/**
 * Newsletter validation rules trait file.
 * php version 8.4
 *
 * @category  App\Traits\Newsletters
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Traits\Newsletters;

/**
 * Newsletter validation rules trait.
 *
 * @category App\Traits\Newsletters
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
trait NewsletterValidationRules
{
    /**
     * Run the validation rule.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function formRules(): array
    {
        $rules = [
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featuredImageUploaded' => 'nullable|image|max:2048',
            'status' => 'in:draft,scheduled,published',
            'tags' => 'array',
            'categories' => 'array',
            'recipients' => 'array',
            'is_published_as_blog' => 'boolean',
        ];

        // Only validate scheduled_at if status is 'scheduled'
        if ($this->status === 'scheduled') {
            $rules['scheduled_at'] = 'required|date|after:now';
        } else {
            $rules['scheduled_at'] = 'nullable|date';
        }

        return $rules;
    }

    /**
     * Validation rules for creating newsletter (simplified - no status/scheduling).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function createRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featuredImageUploaded' => 'nullable|image|max:2048',
            'tags' => 'array',
            'categories' => 'array',
            'recipients' => 'array',
            'is_published_as_blog' => 'boolean',
        ];
    }

    /**
     * Validation rules for sending newsletter (no scheduling validation).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function sendRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featuredImageUploaded' => 'nullable|image|max:2048',
            'tags' => 'array',
            'categories' => 'array',
            'recipients' => 'array',
            'is_published_as_blog' => 'boolean',
        ];
    }
}
