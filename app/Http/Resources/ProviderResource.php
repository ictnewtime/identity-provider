<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource per le rotte API dei provider.
 *
 * Espone solo i campi non sensibili: NON include "secret_key", che resta
 * confinato alle rotte web admin tramite ProviderAdminResource.
 */
class ProviderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "url" => $this->url,
            "domain" => $this->domain,
            "protocol" => $this->protocol,
            "logoutUrl" => $this->logoutUrl,
            "hasTokenUrl" => (bool) $this->has_token_url,
            "deleted_at" => $this->deleted_at,
            // presente solo nella lista (addSelect in all()), assente nel find
            "unique_users_count" => $this->whenHas("unique_users_count"),
        ];
    }
}
