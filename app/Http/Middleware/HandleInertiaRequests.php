<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = "app";

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            ...parent::share($request),
            "auth" => [
                "user" => $request->user(),
            ],
            "locale" => app()->getLocale(),
            "csrf_token" => csrf_token(),
            "flash" => [
                "success" => function () use ($request) {
                    // Usiamo PULL: legge il dato e lo cancella per non farlo riapparire in futuro
                    $msg = $request->session()->pull("success");

                    // if ($msg) {
                    //     \Illuminate\Support\Facades\Log::info("Inertia Flash [SUCCESS] estratto: " . $msg);
                    // }
                    return $msg;
                },
                "error" => function () use ($request) {
                    $msg = $request->session()->pull("error");

                    // if ($msg) {
                    //     \Illuminate\Support\Facades\Log::info("Inertia Flash [ERROR] estratto: " . $msg);
                    // }
                    return $msg;
                },
            ],
        ]);
    }
}
