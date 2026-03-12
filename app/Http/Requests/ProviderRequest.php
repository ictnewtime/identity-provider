<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        // 1. Recuperiamo l'ID dalla rotta (sarà null se siamo in POST/Create)
        $providerId = $this->route("id");

        return [
            "domain" => ["required", "string", "max:255"],

            "secret_key" => [$this->isMethod("post") ? "required" : "sometimes", "string", "max:255"],

            "logoutUrl" => "nullable|url|max:255",
        ];
    }

    public function messages(): array
    {
        return [
            "domain.unique" => __("admin.roles.form.error.domain.unique"),
            "admin.roles.form.error.domain.mandatory" => __("admin.providers.errors.required_domain"),
            "admin.roles.form.error.logout_url.invalid" => __("admin.providers.errors.invalid_logout_url"),
        ];
    }
}
