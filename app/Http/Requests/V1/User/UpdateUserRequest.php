<?php

namespace App\Http\Requests\V1\User;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Route parameter may be a User model (route-model binding) or an int id (controller uses int $id).
        $routeUser = $this->route('user') ?? $this->route('id');

        // Resolve to a User model when necessary
        if (is_numeric($routeUser)) {
            $routeUser = User::find((int) $routeUser);
        }

        if (! $routeUser) {
            // No user found in route — deny authorization
            return false;
        }

        return $this->user()->can('update', $routeUser);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Resolve user id whether route uses model binding or plain id param
        $routeUser = $this->route('user') ?? $this->route('id');
        $userId = null;
        if ($routeUser instanceof User) {
            $userId = $routeUser->id;
        } elseif (is_numeric($routeUser)) {
            $userId = (int) $routeUser;
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($userId),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'status' => ['sometimes', 'nullable', Rule::in(['active', 'suspended', 'banned', 'pending'])],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
            'roles.*.exists' => 'One or more selected roles do not exist.',
        ];
    }
}
