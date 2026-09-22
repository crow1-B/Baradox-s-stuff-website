<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c232b">
    <title>Sign in · {{ config('app.name') }}</title>
    {{-- No Turbo and no player on this layout, so login.js sets up on DOMContentLoaded
         (module scripts run before it) — not turbo:load like every page behind it. --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/css/login.css', 'resources/js/login.js'])
</head>
<body class="lg-body">
    @yield('content')
</body>
</html>
