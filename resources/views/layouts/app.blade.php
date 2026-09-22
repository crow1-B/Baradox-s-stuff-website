<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }}</title>
    {{-- Turbo 8 prefetches links on hover by default; here that would make log.visit record
         every nav link you merely point at as a visited page. --}}
    <meta name="turbo-prefetch" content="false">
    {{-- Tracked so that leaving this layout (e.g. landing on the login page) forces a full
         reload instead of a Turbo body swap. --}}
    <script type="module" src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.23/dist/turbo.es2017-esm.js"
            integrity="sha384-WTluvbYIlhW14A7J29od7iOv+SFs1XBKjEIBArVARW9ppIbzWZPq/MYZ2OatIIFC"
            crossorigin="anonymous" data-turbo-track="reload"></script>
    @vite(['resources/css/app.css', 'resources/css/shell.css', 'resources/css/player.css', 'resources/js/app.js', 'resources/js/shell.js', 'resources/js/player.js'])
    @stack('head')
    <style>
        body {
            background-color: #121212;
            /* dark grey, easier on the eyes than pure black */
            color: white;
        }

        button {
            background-color: #333;
            color: white;
            border: 1px solid #555;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
        }

        button:hover {
            background-color: #555;
        }

        input {
            background-color: #333;
            color: white;
            border: 1px solid #555;
            padding: 6px;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <a class="app-skip" href="#main">Skip to content</a>

    <div class="app-shell" data-app-shell>
        {{-- Below the lg breakpoint the sidebar becomes a drawer opened from this bar. --}}
        <header class="app-topbar">
            <button type="button" class="app-icon-btn" data-app-menu-open aria-controls="app-sidebar" aria-expanded="false" aria-label="Open menu">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            </button>
            <a class="app-brand" href="{{ route('homepage') }}">{{ config('app.name') }}</a>
        </header>

        @include('partials.sidebar')
        <div class="app-backdrop" data-app-menu-close hidden></div>

        <main class="app-main" id="main" tabindex="-1">
            @yield('content')
        </main>
    </div>

    @auth
        @include('partials.player')
    @endauth
</body>

</html>
