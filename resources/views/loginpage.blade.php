@extends('layouts.login_layout')

@section('content')
    @php($loginError = $errors->first('login') ?: $errors->first('username') ?: $errors->first('password'))

    <main class="lg" data-lg>
        {{-- The scene is finished without the canvas: sky, wire and treeline are all CSS/SVG.
             login.js adds the flock on top; if it never runs, nothing here is missing. --}}
        <div class="lg-sky" data-lg-sky>
            {{-- Above the canvas: birds pass behind the title, never over it. --}}
            <header class="lg-intro">
                <h1 class="lg-title">{{ config('app.name') }}</h1>
                <p class="lg-lede">A private place for music, photos, videos and writing.</p>
            </header>

            <svg class="lg-wire" aria-hidden="true" data-lg-wire viewBox="0 0 1000 130" preserveAspectRatio="none" focusable="false">
                {{-- login.js recomputes the first curve's y from these same numbers to put
                     roosting birds on it — keep the two in step. --}}
                <path d="M0 10 Q500 190 1000 10" />
                <path class="lg-wire__low" d="M0 30 Q500 196 1000 30" />
            </svg>
        </div>

        <div class="lg-land">
            <svg class="lg-trees" viewBox="0 -24 1200 64" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                <path d="M0 40 L0 30 Q6 19 11 30 Q16 -12 21 29 Q25 20 29 28 Q33 18 37 27 Q44 14 52 30 Q62 16 72 30 Q77 6 82 28 Q91 15 99 30 Q102 18 106 29 Q111 10 117 29 Q126 8 135 28 Q143 4 150 29 Q161 20 171 28 Q180 -15 188 28 Q198 16 208 28 Q215 13 223 27 Q230 -16 238 27 Q248 16 257 28 Q260 13 264 30 Q267 7 271 29 Q277 5 283 29 Q290 4 298 27 Q303 14 308 27 Q319 19 329 29 Q334 12 339 29 Q342 14 345 28 Q356 8 366 28 Q375 21 383 28 Q393 6 403 29 Q409 -3 415 30 Q420 -5 426 29 Q429 5 432 30 Q437 15 442 30 Q452 2 462 29 Q465 20 469 29 Q476 -21 483 30 Q491 21 498 27 Q508 8 518 29 Q522 7 526 28 Q532 18 538 27 Q548 6 557 28 Q562 12 567 30 Q570 16 573 28 Q584 13 595 27 Q605 15 616 29 Q621 18 625 27 Q635 12 645 28 Q648 9 652 28 Q661 12 670 28 Q676 6 681 29 Q688 3 694 29 Q698 19 702 28 Q706 5 710 28 Q716 11 722 30 Q733 9 743 27 Q750 5 756 29 Q761 16 766 28 Q771 14 776 27 Q782 13 788 27 Q794 4 801 28 Q808 22 815 29 Q818 6 821 29 Q830 11 839 28 Q846 6 854 28 Q859 16 864 28 Q871 7 879 29 Q887 12 894 28 Q901 11 908 27 Q916 4 925 29 Q932 3 940 30 Q945 -7 950 28 Q960 4 969 28 Q977 19 986 27 Q990 3 995 29 Q1006 5 1017 29 Q1024 15 1031 29 Q1040 22 1049 29 Q1052 15 1055 28 Q1059 2 1062 27 Q1068 -18 1074 30 Q1081 4 1087 29 Q1091 4 1095 28 Q1099 21 1103 29 Q1106 3 1110 28 Q1117 -19 1124 29 Q1131 3 1138 30 Q1146 17 1153 30 Q1156 18 1160 29 Q1169 16 1178 29 Q1184 22 1189 30 Q1198 11 1200 29 L1200 40 Z" />
            </svg>

            <div class="lg-main">
                <form class="lg-form" method="POST" action="{{ route('login') }}" data-lg-form="signin">
                    @csrf
                    <div class="lg-field">
                        <label class="lg-label" for="username">Username</label>
                        <input class="lg-input" type="text" id="username" name="username" value="{{ old('username') }}"
                               autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false"
                               required autofocus @if ($loginError) aria-describedby="lg-error" aria-invalid="true" @endif>
                    </div>
                    <div class="lg-field">
                        <label class="lg-label" for="password">Password</label>
                        <input class="lg-input" type="password" id="password" name="password"
                               autocomplete="current-password" required
                               @if ($loginError) aria-describedby="lg-error" aria-invalid="true" @endif>
                    </div>

                    <p class="lg-error" id="lg-error" role="alert" data-lg-error @unless ($loginError) hidden @endunless>{{ $loginError }}</p>

                    <button class="lg-btn lg-btn--primary" type="submit" data-lg-busy="Signing in…">Sign in</button>
                </form>

                <form class="lg-guest" method="POST" action="{{ route('login.guest') }}" data-lg-form="guest">
                    @csrf
                    <p class="lg-guest__lead">Just looking?</p>
                    <p class="lg-guest__note" id="lg-guest-note">Guest opens the whole site with nothing in it — every page, none of the content.</p>
                    <button class="lg-btn lg-btn--ghost" type="submit" aria-describedby="lg-guest-note" data-lg-busy="Opening…">Look around as Guest</button>
                </form>
            </div>

            <footer class="lg-foot">
                <span class="lg-mark">
                    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                        <path d="M30 9.6 24.6 8C23.8 5.7 21.3 4.6 19.2 5.6 17.5 6.4 16.8 8.3 17.2 10 12.9 10.9 9.4 13.6 7.9 17.3L2 26.8l2.7.2 4.7-4.4c1.6.7 3.4.9 5.1.7L13.9 28h1.3l.9-5.2 1.3-.4.1 5.6h1.3l.2-6.2c2.8-1.4 4.6-4.2 4.6-7.4 0-1.5-.3-2.7-.6-3.6z" />
                    </svg>
                    Crow-developers
                </span>
                <span>Made by Baradox</span>
            </footer>
        </div>
    </main>
@endsection
