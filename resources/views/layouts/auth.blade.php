<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
    <title>@yield('title', 'Fakturalista') - Fakturalista</title>
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('assets/icon.svg') }}" />
    <link rel="icon" type="image/png" sizes="32x32" href="{{ url('assets/icon.svg') }}" />
    <link rel="icon" type="image/png" sizes="16x16" href="{{ url('assets/icon.svg') }}" />
    <meta name="theme-color" content="#fa7070" />
    <meta name="author" content="Fakturalista">

    <!-- Dependency Styles (only what an auth form needs) -->
    <link rel="stylesheet" href="{{ url('dependencies/bootstrap/css/bootstrap.min.css') }}" type="text/css" />
    <link rel="stylesheet" href="{{ url('dependencies/fontawesome/css/all.min.css') }}" type="text/css" />

    <!-- Site Stylesheet - same design system as the public site -->
    <link rel="stylesheet" href="{{ url('front/assets/css/app.css') }}" type="text/css" />

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat+Alternates:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet" />

    <style>
        html, body { background: #f5f6fa; }

        /* Shared card shell for every minimal auth page (register, login) -
           kept here so they don't each redefine the same rules. */
        .reg-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 16px;
        }
        .reg-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(20, 20, 40, 0.08);
            padding: 44px 40px;
        }
        .reg-logo-link {
            display: block;
            text-align: center;
            margin-bottom: 32px;
        }
        .reg-logo {
            height: 32px;
        }
        .reg-card-title {
            font-size: 26px;
            font-weight: 800;
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 8px;
        }
        .reg-card-sub {
            font-size: 15px;
            color: #6b7280;
            text-align: center;
            margin-bottom: 28px;
        }
        .reg-note {
            text-align: center;
            font-size: 13px;
            color: #6b7280;
            margin-top: 18px;
        }
        .reg-note i { color: #16a34a; margin-right: 4px; }
        .reg-signin {
            text-align: center;
            font-size: 14px;
            color: #6b7280;
            margin-top: 22px;
            padding-top: 22px;
            border-top: 1px solid #eef0f3;
        }
        .reg-signin a { color: #E91E63; font-weight: 600; text-decoration: none; }
        .reg-signin a:hover { text-decoration: underline; }
        @media (max-width: 576px) {
            .reg-card { padding: 32px 22px; }
        }
    </style>

    @yield('styles')
</head>

<body>

    @yield('content')

</body>

</html>
