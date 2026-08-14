<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Traits\Analytics
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

namespace App\Traits\Analytics;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Traits\Analytics
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
trait LicenseAnalyticValidationRules
{
    /**
     * Run the validation rule.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function formRules(): array
    {
        return [
            'license_id' => 'required|integer',
            'event_name' => 'nullable|string',
            'event_type' => 'nullable|string',
            'app_user_uuid' => 'nullable|string',
            'metadata' => 'nullable|string',
            'ip_address' => 'nullable|string',
            'user_agent' => 'nullable|string',
        ];
    }
}
