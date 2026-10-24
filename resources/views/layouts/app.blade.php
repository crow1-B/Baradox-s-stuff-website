<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-boot')
    {{-- Turbo 8 prefetches links on hover by default; here that would make log.visit record
         every nav link you merely point at as a visited page. --}}
    <meta name="turbo-prefetch" content="false">
    {{-- Turbo wraps each visit in a View Transition; tokens.css silences the root crossfade so
         only elements sharing a view-transition-name morph. --}}
    <meta name="view-transition" content="same-origin">
    {{-- Tracked so that leaving this layout (e.g. landing on the login page) forces a full
         reload instead of a Turbo body swap. --}}
    <script type="module" src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.23/dist/turbo.es2017-esm.js"
            integrity="sha384-WTluvbYIlhW14A7J29od7iOv+SFs1XBKjEIBArVARW9ppIbzWZPq/MYZ2OatIIFC"
            crossorigin="anonymous" data-turbo-track="reload"></script>
    @fonts(['instrument-sans', 'newsreader', 'noto-naskh-arabic'])
    @vite(['resources/css/app.css', 'resources/css/shell.css', 'resources/css/player.css', 'resources/js/app.js', 'resources/js/shell.js', 'resources/js/player.js'])
    @if (config('shell.assets'))
        @vite(config('shell.assets'))
    @endif
    @stack('head')
</head>

<body class="nd-room">
    <a class="app-skip" href="#main">Skip to content</a>

    <div class="app-shell" data-app-shell>
        @include('partials.sidebar')
        <div class="app-backdrop" data-app-menu-close hidden></div>

        <div class="app-column">
            @include('partials.topbar')
            @auth
                @include('partials.backup-attention')
            @endauth

            <main class="app-main" id="main" tabindex="-1">
                @yield('content')
            </main>
        </div>
    </div>

    @auth
        @include('partials.player')
        @include('partials.palette')
    @endauth
</body>

</html>
