<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\Device;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registration of a device's push token for the signed-in account.
 */
class StoreDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route is behind auth.api; the token is always owned by the caller.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // FCM tokens run ~163 chars and APNs ~64; allow generous headroom.
            'token' => ['required', 'string', 'min:16', 'max:512'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'device_id' => ['nullable', 'string', 'max:191'],
            'app_version' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'token.required' => 'A push token is required.',
            'platform.required' => 'A platform is required.',
            'platform.in' => 'The platform must be android or ios.',
        ];
    }
}
