<?php

namespace App\Http\Resources;

/**
 * Resource per le rotte WEB admin dei provider.
 *
 * Estende ProviderResource aggiungendo "secret_key": va usata SOLO sulle rotte
 * protette da role:admin (sessione web), mai sulle rotte API.
 */
class ProviderAdminResource extends ProviderResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return array_merge(parent::toArray($request), [
            "secret_key" => $this->secret_key,
        ]);
    }
}
