<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Traits\Versions
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

namespace App\Traits\Versions;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Traits\Versions
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
trait VersionValidationRules
{
    /**
     * Run the validation rule.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function formRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'description' => 'nullable|string',
            'is_active' => 'nullable|bool',
            'sorting' => 'nullable|integer',
            'software_id' => 'required|integer',
            'changelog' => 'nullable|string',
            'version_number' => 'nullable|string|max:50',
            'release_date' => 'nullable|date',
            'file_path' => 'nullable|string|max:500',
            'file_name' => 'nullable|string|max:255',
            'file_size' => 'nullable|integer|min:0',
            'file_type' => 'nullable|string|max:100',
            'metadata' => 'nullable|array', // Changed from 'json' since we decode it in prepareForValidation
            'software_file' => 'nullable|file|max:4194304', // 4GB max (4096 MB * 1024 KB)
            'imageUploaded' => 'nullable|image|mimes:jpg,png,webp,gif,jpeg|max:10240', // 10MB max
            'terms_file' => 'nullable|file|max:10240', // 10MB max for terms - extension checked in controller
            'privacy_file' => 'nullable|file|max:10240', // 10MB max for privacy - extension checked in controller
        ];
    }
}
