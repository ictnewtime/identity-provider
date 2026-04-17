<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProviderUserRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            "user_id" => ["required", "integer", "exists:users,id"],
            "role_id" => ["required", "integer", "exists:roles,id"],
            "provider_id" => ["required", "integer", "exists:providers,id"],
        ];

        // Spostiamo il controllo di unicità su 'role_id'
        if ($this->isMethod("POST")) {
            $rules["role_id"][] = Rule::unique("provider_user_roles", "role_id")->where(function ($query) {
                return $query
                    ->where("user_id", $this->input("user_id"))
                    ->where("provider_id", $this->input("provider_id"))
                    ->whereNull("deleted_at");
            });
        }

        // Stessa cosa per l'Update (PUT/PATCH)
        if ($this->isMethod("PUT") || $this->isMethod("PATCH")) {
            $id = $this->route("id");

            $rules["role_id"][] = Rule::unique("provider_user_roles", "role_id")
                ->where(function ($query) {
                    return $query
                        ->where("user_id", $this->input("user_id"))
                        ->where("provider_id", $this->input("provider_id"))
                        ->whereNull("deleted_at");
                })
                ->ignore($id);
        }

        return $rules;
    }

    /**
     * Personalizza il messaggio di errore per renderlo chiaro all'utente
     */
    public function messages(): array
    {
        return [
            // Aggiorniamo la chiave per mostrare l'errore sotto il Ruolo
            "role_id.unique" => __("admin.provider_user_roles.errors.unique_role"),
        ];
    }
}
