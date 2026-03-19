<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->route("user") ?? $this->route("id");

        return [
            "email" => ["required", "email", Rule::unique("users", "email")->ignore($userId)],
            "username" => ["required", "string", Rule::unique("users", "username")->ignore($userId)],
            "name" => "required|string|max:255",
            "surname" => "required|string|max:255",
            "password" => $this->isMethod("post") ? "required|min:12|confirmed" : "sometimes|nullable|min:12|confirmed",
            "password_confirmation" => $this->isMethod("post") ? "required|min:12" : "sometimes|nullable|min:12",
            "password_expires_at" => "nullable|date",
        ];
    }
}
