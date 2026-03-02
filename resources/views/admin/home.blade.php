<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Identity Provider - Admin</title>

        <link rel="icon" type="image/png" href="/images/favicon.png" />

        <link rel="stylesheet" href="/css/app.css">
        <script defer src="https://use.fontawesome.com/releases/v5.7.2/js/all.js"
                integrity="sha384-0pzryjIRos8mFBWMzSSZApWtPl/5++eIfzYmTgBBmXYdhvxPc+XcFEk+zJwDgWbP" crossorigin="anonymous">
        </script>

        <link rel="stylesheet" href="/css/admin.css">
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    </head>
    <body class="bg-white">

        <div id="nav-bar" class="w-100 position-fixed">
            <div class="bg-dark px-3 py-4 text-white d-flex justify-content-between">
                <b><p class="text-white mb-0">PANNELLO AMMINISTRAZIONE</p></b>

            <p class="nav-item">
                <a class="nav-link active text-white mb-0" href="{{ route('logout') }}">@lang('auth.label-logout')</a>
            </p>
            </div>
        </div>


        <div id="app" class="h-100">

            <div id="side-menu" class="h-100 position-fixed pt-2 px-0">

                <div>
                    <a href="{{ route('web-users') }}" class="text-dark no-text-decoration">
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fa fa-users fa-lg"></i>
                            </div>
                            <div>
                                <span>UTENTI</span>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('web-roles') }}" class="text-dark no-text-decoration">
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fa fa-key fa-lg"></i>
                            </div>
                            <div>
                                <span>RUOLI</span>
                            </div>
                        </div>
                    </a>

                    <!-- <a href="#" class="text-dark no-text-decoration">
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fa fa-briefcase fa-lg"></i>
                            </div>
                            <div>
                                <span>DIPARTIMENTI</span>
                            </div>
                        </div>
                    </a> -->

                    <a href="{{ route('web-providers') }}" class="text-dark no-text-decoration">
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fa fa-server fa-lg"></i>
                            </div>
                            <div>
                                <span>PROVIDER</span>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('web-provider-user-roles') }}" class="text-dark no-text-decoration">
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fa fa-user-tag fa-lg"></i>
                            </div>
                            <div>
                                <span>PROVIDER-UTENTI-RUOLI</span>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('web-sessions') }}" class="text-dark no-text-decoration">
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fa fa-history fa-lg"></i>
                            </div>
                            <div>
                                <span>SESSIONI</span>
                            </div>
                        </div>
                    </a>

                    <a href="{{ route('oauth-clients') }}" class="text-dark no-text-decoration" hidden>
                        <div class="d-flex py-4 border-bottom border-light align-items-center px-4 menu-item">
                            <div class="pr-3">
                                <i class="fas fa-user-lock"></i>
                            </div>
                            <div>
                                <span>CLIENTS</span>
                            </div>
                        </div>
                    </a>

                    <p class="text-center mt-5">Version 1.0.1</p>
                </div>

            </div>
            <div id="content" class="px-5 pb-5">
                <div class="col-12 col-xl-10 position-static">
                    <notification></notification>
                    @yield('content')
                </div>
            </div>
        </div>

        <!-- <script src="{{mix('js/app.js')}}"></script> -->
    </body>
</html>