<?php

namespace App\Http\Controllers\Manage\Web;

use App\Http\Controllers\Manage\ProviderController as ApiProviderController;
use App\Http\Resources\ProviderAdminResource;
use App\Models\Provider;
use Illuminate\Http\Request;

/**
 * Controller dedicato alle rotte WEB (admin) dei provider.
 *
 * Riusa tutta la logica del controller API ed espone in più l'attributo
 * "secret_key" sulle sole letture (all, find), tramite ProviderAdminResource.
 * Le rotte API continuano a usare ProviderResource, che la "secret_key" non la
 * espone mai. Queste rotte sono protette da middleware role:admin.
 */
class ProviderController extends ApiProviderController
{
    public function all(Request $request)
    {
        return $this->buildProvidersQuery($request)->through(
            fn(Provider $provider) => new ProviderAdminResource($provider),
        );
    }

    public function find($id)
    {
        $provider = Provider::withTrashed()->find($id);

        if (empty($provider)) {
            return response()->json(["message" => "Provider not found"], 404);
        }

        return response()->json(["provider" => new ProviderAdminResource($provider)], 200);
    }
}
