<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Repositories\ClientRoleRepository;
use OpenApi\Attributes as OA;

class ClientRoleController extends Controller
{
    private $clientRoleRepository;

    public function __construct(ClientRoleRepository $clientRoleRepository)
    {
        $this->clientRoleRepository = $clientRoleRepository;
    }


    #[OA\Get(
        path: '/v1/client-roles',
        summary: 'list of  client roles',
        description: 'Returns the entire list of client roles',
        operationId: 'ClientRole.all',
        tags: ['ClientRoles'],
        security: [['web' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Operation successful',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                ),
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                ),
            ),
        ]
    )]
    public function all(){

        return $this->clientRoleRepository->all();
    }
}
