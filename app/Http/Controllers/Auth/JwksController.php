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
            abort(404, __("jwks.public_key_not_found"));
        }

        $publicKeyPem = File::get($publicKeyPath);
        $publicKey = openssl_pkey_get_public($publicKeyPem);
        $keyDetails = openssl_pkey_get_details($publicKey);

        if (!$keyDetails || !isset($keyDetails["rsa"])) {
            abort(500, __("jwks.unable_to_extract_public_key_rsa"));
        }

        $base64UrlEncode = function ($data) {
            return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
        };

        $jwks = [
            "keys" => [
                [
                    "kty" => "RSA", // Key Type
                    "alg" => "RS256", // Algorithm
                    "use" => "sig", // Public Key Use
                    "kid" => config("idp.jwt.master_key_id"), // Key ID
                    "n" => $base64UrlEncode($keyDetails["rsa"]["n"]), // Modulus
                    "e" => $base64UrlEncode($keyDetails["rsa"]["e"]), // Exponent
                ],
            ],
        ];

        return response()->json($jwks, 200, [
            "Content-Type" => "application/json",
            "Access-Control-Allow-Origin" => "*",
        ]);
    }
}
