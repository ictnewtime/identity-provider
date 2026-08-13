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

    public const OA_DESC_MSG_SUCCESS = "Operation successful"; // 200
    public const OA_DESC_MSG_CREATED = "Resource created successfully"; // 201
    public const OA_DESC_MSG_BAD_REQUEST = "Bad request"; // 400
    public const UNAUTHORIZED = "Unauthorized"; // 401
    public const OA_DESC_MSG_FORBIDDEN = "Forbidden"; // 403
    public const OA_DESC_MSG_NOT_FOUND = "Not found"; // 404
    public const OA_DESC_MSG_UNPROCESSABLE_ENTITY = "Unprocessable entity"; // 422
    public const OA_DESC_MSG_INTERNAL_SERVER_ERROR = "Internal server error"; // 500

    public const MEDIA_TYPE_JSON = "application/json";

    public const OA_DESC_PROVIDER_ID = "Provider id";
    public const OA_DESC_ROLE_ID = "Role id";
    public const OA_DESC_USER_ID = "User id";

    public const OA_DESC_MSG_SECURITY_ADMIN = "__*Security:*__ __*can be used only by clients with \'admin\' role*__";
}
