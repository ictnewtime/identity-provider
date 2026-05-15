<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;

class JwksController extends Controller
{
    public function index()
    {
        $publicKeyPath = storage_path("app/keys/public.key");

        if (!File::exists($publicKeyPath)) {
            return response()->json(["error" => __("jwks.public_key_not_found")], 404);
        }

        $publicKeyPem = File::get($publicKeyPath);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        $keyDetails = openssl_pkey_get_details($publicKey);

        if (!$keyDetails || !isset($keyDetails["rsa"])) {
            return response()->json(["error" => __("jwks.unable_to_extract_public_key_rsa")], 500);
        }

        $base64UrlEncode = function ($data) {
            return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
        };

        $jwks = [
            "keys" => [
                [
                    "kty" => "RSA",
                    "alg" => "RS256",
                    "use" => "sig",
                    "kid" => config("idp.jwt.master_key_id"),
                    "n" => $base64UrlEncode($keyDetails["rsa"]["n"]),
                    "e" => $base64UrlEncode($keyDetails["rsa"]["e"]),
                ],
            ],
        ];

        return response()->json($jwks, 200, [
            "Content-Type" => "application/json",
            "Access-Control-Allow-Origin" => "*",
        ]);
    }
}
