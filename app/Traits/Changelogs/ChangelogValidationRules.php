<?php

declare(strict_types=1);

/**
 * Validation rules trait file.
 * php version 8.4
 *
 * @category  App\Traits\Changelogs
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

namespace App\Traits\Changelogs;

/**
 * Validation rules trait for Changelog Model.
 *
 * @category App\Traits\Changelogs
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
trait ChangelogValidationRules
{
    /**
     * Run the validation rule.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function changelogRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'file_path' => 'nullable|string|max:500',
            'file_name' => 'nullable|string|max:255',
            'version_number' => 'nullable|string|max:50',
            'release_date' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'sorting' => 'nullable|integer|min:0',
            'changelogable_type' => 'required|string',
            'changelogable_id' => 'required|integer',
            'changelog_file' => 'nullable|file|mimes:md,txt|max:10240', // 10MB max, markdown or text files
        ];
    }

    /**
     * Form validation rules for creating changelogs.
     */
    public function createChangelogRules(): array
    {
        return $this->changelogRules();
    }

    /**
     * Form validation rules for updating changelogs.
     */
    public function updateChangelogRules(): array
    {
        $rules = $this->changelogRules();

        // Make some fields optional for updates
        $rules['changelogable_type'] = 'sometimes|required|string';
        $rules['changelogable_id'] = 'sometimes|required|integer';

        return $rules;
    }
}
