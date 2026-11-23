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
     * Un form (application/x-www-form-urlencoded, come lo manda Swagger) invia `has_token_url=true`
     * come testo, e la regola `boolean` di Laravel rifiuta la stringa "true" con un 422.
     * Qui la si converte in un booleano vero: true/false, 1/0, on/off, yes/no.
     * Un valore non riconosciuto resta com'e', cosi' la regola `boolean` lo rifiuta ancora.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has("has_token_url")) {
            return;
        }

        $value = filter_var($this->input("has_token_url"), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($value !== null) {
            $this->merge(["has_token_url" => $value]);
        }
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

            "logoutUrl" => "required|url|max:255",

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
            "logoutUrl.required" => __("admin.roles.form.error.logout_url.mandatory"),
            "logoutUrl.url" => __("admin.roles.form.error.logout_url.invalid"),
            "logoutUrl.max" => __("admin.roles.form.error.logout_url.too_long"),
        ];
    }
}
