<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Device;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Detachment of a device's push token on sign-out.
 *
 * Only the token is required: it is unique across the table, so it is enough to
 * identify the row without exposing another user's device to the caller.
 */
class DestroyDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is behind auth.api; the controller scopes the delete to the
        // authenticated user, so a token belonging to someone else is a no-op.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:16', 'max:512'],
        ];
    }
}
