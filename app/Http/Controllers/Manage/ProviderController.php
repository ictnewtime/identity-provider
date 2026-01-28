<?php

namespace App\Http\Controllers\Manage;

use App\Http\Controllers\Controller;
use App\Repositories\RepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class ProviderController extends Controller
{
    protected $providerRepository;

    public function __construct(RepositoryInterface $providerRepository)
    {
        $this->providerRepository = $providerRepository;
    }

    #[OA\Post(
        path: '/v1/providers',
        summary: 'Create a new provider',
        description: '__*Security:*__ __*can be used only by clients with \'admin\' role*__',
        operationId: 'Provider.create',
        tags: ['Providers'],
        security: [ ['passport' => [] ] ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'domain',
                            description: 'Provider domain',
                            type: 'string',
                            example: 'portale.newtimegroup.it'
                        ),
                        new OA\Property(
                            property: 'logoutUrl',
                            description: 'URL for logout',
                            type: 'string',
                            example: 'https://portale.newtimegroup.it/logoutUser/'
                        ),
                        new OA\Property(
                            property: 'username',
                            description: 'username',
                            type: 'string',
                            example: '4d8d58c9s99'
                        ),
                        new OA\Property(
                            property: 'password',
                            description: 'password',
                            type: 'string',
                            example: '4sc8s28v4d8s'
                        ),
                    ],
                    required: ['domain', 'logoutUrl', 'username', 'password']
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
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
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                ),
            ),
            new OA\Response(
                response: 500,
                description: 'Server error',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                )
            )
        ]
    )]
    public function create(Request $request)
    {
        $data = $request->only('domain', 'username', 'password', 'logoutUrl');

        $validator = $this->validator($data);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data is invalid',
                'errors' => $validator->errors()
            ], 422);
        }

        // TODO probably if the creating fails throws an exception
        $provider = $this->providerRepository->create($data);

        if (empty($provider)) {
            return response()->json([
                'message' => 'Error during saving provider'
            ], 500);
        }

        return response()->json([
            'provider' => $provider
        ], 201);
    }

    #[OA\Get(
        path: '/v1/providers',
        summary: 'list of providers',
        description: 'Returns the entire list of providers',
        operationId: 'Provider.all',
        tags: ['Providers'],
        security: [ ['passport' => [] ] ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Operation successful',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\MediaType(
                    mediaType: 'application/json',
                )
            )
        ]
    )]
    public function all()
    {
        return $this->providerRepository->all();
    }

    /*
    * Returns the validator for user data
    */
    private function validator(array $data)
    {
        return Validator::make($data, [
            'domain' => 'required|string|unique:providers|max:255',
            'username' => 'required|string|max:50',
            'password' => 'required|string|min:5|max:50',
            'logoutUrl' => 'string|max:255|nullable',
        ]);
    }
}
