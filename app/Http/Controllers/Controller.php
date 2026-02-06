<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

// const L5_SWAGGER_CONST_TOKEN_URL = env('L5_SWAGGER_CONST_TOKEN_URL', config('app.url').'/v2/login');
#[
    OA\Info(
        version: "1.0.0",
        title: "My API",
        description: "API description",
        contact: new OA\Contact(email: "contact@example.com"),
        license: new OA\License(name: "Apache 2.0", url: "https://www.apache.org/licenses/LICENSE-2.0.html"),
    ),
]
#[OA\Tag(name: "Roles", description: "Handle roles operations")]
#[
    OA\SecurityScheme(
        securityScheme: "passport",
        type: "oauth2",
        description: "OAuth2 Client Credentials Flow",
        flows: [new OA\Flow(flow: "clientCredentials", tokenUrl: "/oauth/token", scopes: [])],
    ),
]
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
