<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Traits\Licenses
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

namespace App\Traits\Licenses;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Traits\Licenses
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
trait LicenseValidationRules
{
    /**
     * Run the validation rule.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function formRules(): array
    {
        return [
            'user_id' => 'required|integer',
            'software_id' => 'required|integer',
            'version_id' => 'required|integer',
            'package_id' => 'required|integer',
            'ltype_id' => 'required|integer',
            'hardware_id' => 'nullable|string',
            'license_key' => 'nullable|string',
            'user_message' => 'nullable|string',
            'data_file' => 'nullable|string',
            'license_file' => 'nullable|string',
            'status' => 'nullable|string',
            'is_active' => 'nullable|bool',
            'features' => 'nullable|string',
            'modules' => 'nullable|string',
            'expires_at' => 'nullable|string',
        ];
    }
}
