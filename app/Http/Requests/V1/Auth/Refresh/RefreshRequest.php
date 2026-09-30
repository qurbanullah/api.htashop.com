<?php

namespace App\Http\Requests\V1\Auth\Refresh;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A refresh attempt.
 *
 * The token is deliberately not `required`: browsers send it in an httpOnly
 * cookie and native clients in the X-Refresh-Token header, so a missing body
 * field must still reach the controller and fail as a 401 (a single, consistent
 * "your session is over" response) rather than a 422 validation error.
 */
class RefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'refresh_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
