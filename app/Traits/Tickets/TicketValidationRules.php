<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Traits\Tickets
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

namespace App\Traits\Tickets;

use App\Enums\PriorityEnum;
use App\Enums\ReproducibilityEnum;
use App\Enums\SeverityEnum;
use App\Enums\StatusEnum;
use App\Enums\StypeEnum;
use Illuminate\Validation\Rules\Enum;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Traits\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
trait TicketValidationRules
{
    /**
     * Run the validation rule.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function formRules(): array
    {
        return [
            'uuid' => 'required|string|max:255',
            'user_id' => 'required|int|max:255',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'stype' => ['required', new Enum(StypeEnum::class)],
            'severity' => ['required', new Enum(SeverityEnum::class)],
            'reproducibility' => ['required', new Enum(ReproducibilityEnum::class)],
            'priority' => ['required', new Enum(PriorityEnum::class)],
            'description' => 'required|string',
            'steps_to_reproduce' => 'nullable|string',
            'additional_information' => 'nullable|string',
            'status' => ['required', new Enum(StatusEnum::class)],
            'is_visible' => 'nullable|bool',
            'is_resolved' => 'nullable|bool',
            'is_archived' => 'nullable|bool',
            'resolved_on' => 'nullable|string|max:255',
            'archived_on' => 'nullable|string|max:255',
        ];
    }
}
