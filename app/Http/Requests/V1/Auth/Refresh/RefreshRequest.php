<?php

namespace App\Http\Requests\V1\Auth\Refresh;

use Illuminate\Foundation\Http\FormRequest;

class RefreshRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
