<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProviderRequest extends FormRequest
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
        return [
            "domain" => ["required", "string", "max:255"],

            "secret_key" => [$this->isMethod("post") ? "required" : "sometimes", "string", "max:255"],

            "logoutUrl" => "nullable|url|max:255",

            "has_token_url" => ["sometimes", "boolean"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            "domain.required" => __("admin.roles.form.error.domain.mandatory"),
            "logoutUrl.url" => __("admin.roles.form.error.logout_url.invalid"),
        ];
    }
}
