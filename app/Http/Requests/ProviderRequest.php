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
            "domain.unique" => "Il dominio inserito è già registrato a sistema.",
            "domain.required" => "Il campo dominio è obbligatorio.",
            "logoutUrl.url" => 'Il formato dell\'URL di logout non è valido.',
        ];
    }
}
