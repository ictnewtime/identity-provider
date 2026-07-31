<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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

    protected function prepareForValidation()
    {
        $this->merge([
            "enabled" => filter_var($this->enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
        ]);
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
            "password" => [
                $this->isMethod("post") ? "required" : "sometimes",
                "nullable",
                "min:12",
                "confirmed",
                // In update: la nuova password deve essere diversa dall'attuale.
                // In create ($userId null) non c'è una vecchia password da confrontare.
                function ($attribute, $value, $fail) use ($userId) {
                    if (!$value || !$userId) {
                        return;
                    }
                    $user = User::find($userId);
                    if ($user && Hash::check($value, $user->password)) {
                        $fail(__("auth.password_same_as_old"));
                    }
                },
            ],
            "password_confirmation" => $this->isMethod("post") ? "required|min:12" : "sometimes|nullable|min:12",
            "password_expires_at" => "nullable|date",
            "enabled" => "sometimes|boolean",
        ];
    }

    public function messages(): array
    {
        return [
            "email.required" => __("users.validation.email_required"),
            "email.email" => __("users.validation.email_invalid"),
            "email.unique" => __("users.validation.email_unique"),

            "username.required" => __("users.validation.username_required"),
            "username.unique" => __("users.validation.username_unique"),
        ];
    }
}
