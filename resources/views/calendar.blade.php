@extends('layouts.app')

@push('head')
    @vite(['resources/css/calendar.css', 'resources/js/calendar.js'])
@endpush

@section('content')
@php
    use App\Http\Controllers\CalendarController;

    $monthUrl = fn ($m, array $extra = []) => route('calendar', ['month' => $m->format('Y-m')] + $extra);
    $prev = $month->copy()->subMonth();
    $next = $month->copy()->addMonth();
    $isThisMonth = $month->isSameMonth($today);
    $isThisYear = $month->year === $today->year;

    $layerNames = [
        'diary' => ['Diary', 'fa-feather'],
        'projects' => ['Projects', 'fa-diagram-project'],
        'photos' => ['Photos', 'fa-image'],
        'videos' => ['Videos', 'fa-film'],
        'tracks' => ['Songs added', 'fa-music'],
        'listening' => ['Listening', 'fa-headphones'],
    ];

    $plural = fn ($n, $word) => $n . ' ' . Str::plural($word, $n);

    // What the day's button says beyond its date, one span per layer so a hidden layer
    // drops out of the accessible name with the marks it describes.
    $summary = function ($day) use ($plural) {
        $parts = [];
        $entries = count($day['entries']);
        $written = count($day['written']);
        if ($entries || $written) {
            $text = [];
            if ($entries) $text[] = $plural($entries, 'entry');
            if ($written) $text[] = $plural($written, 'entry') . ' written';
            $parts['diary'] = implode(', ', $text);
        }
        if ($day['projects']) {
            $parts['projects'] = $plural(count($day['projects']), 'project') . ' starting or finishing';
        }
        $photos = count($day['photos']) + $day['lockedPhotos'];
        if ($photos) $parts['photos'] = $plural($photos, 'photo') . ' added';
        if ($day['videos']) $parts['videos'] = $plural(count($day['videos']), 'video') . ' registered';
        if ($day['tracks']) $parts['tracks'] = $plural(count($day['tracks']), 'song') . ' added';
        if ($day['listening']) $parts['listening'] = $plural($day['listening']['plays'], 'play');
        return $parts;
    };

    $weekdays = [];
    for ($d = $weeks[0]['days'][array_key_first($weeks[0]['days'])]['date']->copy(), $i = 0; $i < 7; $i++, $d->addDay()) {
        $weekdays[] = [$d->format('D'), $d->format('l')];
    }

    $heatLevel = fn ($n) => min(4, $n);
    $yearStart = $month->copy()->startOfYear();
    $yearTotal = array_sum($heatmap);
@endphp

<div class="nd-page nd-page--wide cal @foreach ($defaultLayers as $layer) cal--{{ $layer }} @endforeach"
     id="cal-root" data-view="{{ $view }}" data-month="{{ $month->format('Y-m') }}"
     data-default-layers="{{ implode(' ', $defaultLayers) }}" data-layers="{{ implode(' ', $layers) }}"
     data-user="{{ Auth::id() }}">
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Calendar</h1>
            <p class="nd-page__sub">When things happened, and when you wrote them down.</p>
        </div>
        <div class="nd-page__actions">
            <div class="nd-segmented" role="radiogroup" aria-label="View">
                <input type="radio" name="cal-view" id="cal-view-month" value="month" data-cal-view-choice @checked($view === 'month')>
                <label for="cal-view-month">Month</label>
                <input type="radio" name="cal-view" id="cal-view-year" value="year" data-cal-view-choice @checked($view === 'year')>
                <label for="cal-view-year">Year</label>
            </div>
        </div>
    </header>

    <div class="cal-bar">
        <nav class="cal-nav cal-nav--month" aria-label="Months">
            <a class="nd-icon-btn" href="{{ $monthUrl($prev) }}" aria-label="Previous month, {{ $prev->format('F Y') }}" data-cal-step="-1">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </a>
            <h2 class="cal-nav__title" id="cal-month-title">{{ $month->format('F') }} <span>{{ $month->year }}</span></h2>
            <a class="nd-icon-btn" href="{{ $monthUrl($next) }}" aria-label="Next month, {{ $next->format('F Y') }}" data-cal-step="1">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
            @unless ($isThisMonth)
                <a class="nd-btn nd-btn--sm" href="{{ route('calendar') }}">Today</a>
            @endunless
        </nav>

        <nav class="cal-nav cal-nav--year" aria-label="Years">
            <a class="nd-icon-btn" href="{{ $monthUrl($month->copy()->subYear(), ['view' => 'year']) }}" aria-label="Previous year, {{ $month->year - 1 }}">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </a>
            <h2 class="cal-nav__title" id="cal-year-title">{{ $month->year }}</h2>
            <a class="nd-icon-btn" href="{{ $monthUrl($month->copy()->addYear(), ['view' => 'year']) }}" aria-label="Next year, {{ $month->year + 1 }}">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
            </a>
            @unless ($isThisYear)
                <a class="nd-btn nd-btn--sm" href="{{ route('calendar', ['view' => 'year']) }}">This year</a>
            @endunless
        </nav>

        <div class="cal-layers" role="group" aria-label="Show on the calendar">
            @foreach ($layers as $layer)
                <button type="button" class="nd-chip" data-cal-layer="{{ $layer }}" aria-pressed="{{ in_array($layer, $defaultLayers, true) ? 'true' : 'false' }}">
                    <i class="fa-solid {{ $layerNames[$layer][1] }}" aria-hidden="true"></i>{{ $layerNames[$layer][0] }}
                </button>
            @endforeach
        </div>
    </div>

    @if ($isEmpty)
        <p class="nd-note nd-note--info cal-empty cal-empty--month">
            <i class="fa-solid fa-calendar" aria-hidden="true"></i>
            <span>Nothing dated in {{ $month->format('F Y') }}.</span>
        </p>
    @endif

    <div class="cal-views" data-cal-views>
        {{-- ------------------------------------------------------------ month --}}
        <section class="cal-month" aria-labelledby="cal-month-title">
            <div class="cal-layout">
                <div class="cal-grid" data-cal-grid aria-describedby="cal-grid-hint">
                    <p class="nd-sr-only" id="cal-grid-hint">Arrow keys move between days, Page Up and Page Down between months.</p>
                    <div class="cal-dow" aria-hidden="true">
                        @foreach ($weekdays as [$short, $long])
                            <span title="{{ $long }}">{{ $short }}</span>
                        @endforeach
                    </div>

                    @foreach ($weeks as $week)
                        <div class="cal-week cal-week--lanes-{{ $week['lanes'] }}">
                            @foreach (array_values($week['days']) as $i => $day)
                                @php
                                    $key = $day['date']->toDateString();
                                    $classes = 'cal-c' . ($i + 1)
                                        . ($day['date']->isSameMonth($month) ? '' : ' is-out')
                                        . ($day['date']->equalTo($today) ? ' is-today' : '')
                                        . ($key === $selected->toDateString() ? ' is-selected' : '');
                                @endphp
                                <div class="cal-cell {{ $classes }}" data-cal-cell="{{ $key }}"></div>
                                <button type="button" class="cal-num {{ $classes }}" data-cal-day="{{ $key }}"
                                        tabindex="{{ $key === $selected->toDateString() ? '0' : '-1' }}"
                                        aria-pressed="{{ $key === $selected->toDateString() ? 'true' : 'false' }}"
                                        aria-controls="cal-day-{{ $key }}"
                                        @if ($day['date']->equalTo($today)) aria-current="date" @endif>
                                    <span aria-hidden="true">{{ $day['date']->day }}</span>
                                    <span class="nd-sr-only">{{ $day['date']->format('l j F Y') }}@if ($day['date']->equalTo($today)), today @endif</span>
                                    @foreach ($summary($day) as $layer => $text)
                                        <span class="nd-sr-only" data-layer="{{ $layer }}">, {{ $text }}</span>
                                    @endforeach
                                </button>
                            @endforeach

                            @foreach ($week['bars'] as $bar)
                                @php
                                    $place = "cal-from-{$bar['col']} cal-to-" . ($bar['end'] + 1) . " cal-lane-{$bar['lane']}";
                                    $item = $bar['item'];
                                @endphp
                                @if ($bar['kind'] === 'project')
                                    <a class="cal-span cal-span--project {{ $place }} @if ($bar['startsHere']) is-start @endif @if ($bar['endsHere'] && !$bar['openEnded']) is-end @endif @if ($bar['openEnded']) is-open @endif"
                                       href="{{ $item['url'] }}" tabindex="-1" data-layer="projects" data-status="{{ $item['status'] }}">
                                        <span class="cal-span__label" dir="auto">{{ $item['title'] }}</span>
                                        <span class="nd-sr-only">, {{ $item['statusLabel'] }}, {{ $item['start']->format('j M Y') }} to {{ $item['finish'] ? $item['finish']->format('j M Y') : 'no finish date yet' }}</span>
                                    </a>
                                @else
                                    @php $eventFirst = $item['event']->lte($item['written']); @endphp
                                    {{-- The gap between the day it happened and the day it was written: a dot
                                         on the event, a ring on the writing. Described in words in the panel. --}}
                                    <span class="cal-span cal-span--gap {{ $place }}" data-layer="diary" data-cal-entry="{{ $item['id'] }}" aria-hidden="true"
                                          @if ($item['mood']) data-mood="{{ $item['mood'] }}" @endif>
                                        @if ($bar['startsHere'])
                                            <span class="cal-mark cal-mark--{{ $eventFirst ? 'event' : 'written' }} cal-span__start"></span>
                                        @endif
                                        @if ($bar['endsHere'])
                                            <span class="cal-mark cal-mark--{{ $eventFirst ? 'written' : 'event' }} cal-span__end"></span>
                                        @endif
                                    </span>
                                @endif
                            @endforeach

                            @foreach (array_values($week['days']) as $i => $day)
                                @php
                                    $slips = array_merge($day['entries'], array_map(fn ($e) => $e + ['isWritten' => true], $day['written']));
                                    $shown = array_slice($slips, 0, 3);
                                    $photoTotal = count($day['photos']) + $day['lockedPhotos'];
                                    $hidden = $week['hidden'][$i + 1] ?? 0;
                                @endphp
                                <div class="cal-marks cal-c{{ $i + 1 }}" data-cal-marks="{{ $day['date']->toDateString() }}">
                                    @foreach ($shown as $entry)
                                        @if ($entry['locked'])
                                            <a class="cal-slip cal-slip--sealed" href="{{ $entry['url'] }}" data-morph="dy-entry-{{ $entry['id'] }}" tabindex="-1" data-layer="diary">
                                                <i class="fa-solid fa-lock" aria-hidden="true"></i><span>Sealed entry</span>
                                            </a>
                                        @else
                                            <a class="cal-slip @isset($entry['isWritten']) cal-slip--written @endisset" href="{{ $entry['url'] }}" data-morph="dy-entry-{{ $entry['id'] }}"
                                               tabindex="-1" data-layer="diary" data-cal-entry="{{ $entry['id'] }}" @if ($entry['mood']) data-mood="{{ $entry['mood'] }}" @endif>
                                                <span class="cal-mark cal-mark--{{ isset($entry['isWritten']) ? 'written' : 'event' }}" aria-hidden="true"></span>
                                                <span class="cal-slip__title nd-written" dir="auto">{{ $entry['title'] }}</span>
                                                <span class="nd-sr-only">{{ isset($entry['isWritten']) ? ', written this day about ' . $entry['event']->format('j F') : ($entry['gap'] ? ', written ' . $entry['written']->format('j F') : '') }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                    @if (count($slips) > 3)
                                        <span class="cal-more" data-layer="diary" aria-hidden="true">+{{ count($slips) - 3 }} more</span>
                                    @endif
                                    @if ($hidden)
                                        <span class="cal-more" aria-hidden="true">+{{ $hidden }} not shown</span>
                                    @endif
                                    <span class="cal-counts" aria-hidden="true">
                                        @if ($photoTotal)
                                            <span class="cal-count" data-layer="photos"><i class="fa-solid {{ $day['photos'] ? 'fa-image' : 'fa-lock' }}"></i>{{ $photoTotal }}</span>
                                        @endif
                                        @if ($day['videos'])
                                            <span class="cal-count" data-layer="videos"><i class="fa-solid fa-film"></i>{{ count($day['videos']) }}</span>
                                        @endif
                                        @if ($day['tracks'])
                                            <span class="cal-count" data-layer="tracks"><i class="fa-solid fa-music"></i>{{ count($day['tracks']) }}</span>
                                        @endif
                                        @if ($day['listening'])
                                            <span class="cal-count" data-layer="listening"><i class="fa-solid fa-headphones"></i>{{ $day['listening']['plays'] }}</span>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="cal-panel" data-cal-panel-list>
                    @if ($monthProjects->isNotEmpty())
                        <section class="cal-running" data-layer="projects" aria-labelledby="cal-running-title">
                            <h3 class="cal-running__title" id="cal-running-title">Projects in {{ $month->format('F') }}</h3>
                            <ul class="cal-items" role="list">
                                @foreach ($monthProjects as $project)
                                    <li>
                                        <a class="cal-item cal-item--compact" href="{{ $project['url'] }}">
                                            <span class="cal-item__icon" aria-hidden="true"><span class="nd-dot nd-dot--{{ $project['status'] }}"></span></span>
                                            <span class="cal-item__main">
                                                <span class="cal-item__title" dir="auto">{{ $project['title'] }}</span>
                                                <span class="cal-item__meta">{{ $project['start']->format('j M') }} – {{ $project['finish'] ? $project['finish']->format('j M Y') : 'open' }} · {{ $project['statusLabel'] }}</span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    @unless ($isEmpty)
                        <p class="cal-agenda-empty">Nothing in {{ $month->format('F') }} with these layers shown.</p>
                    @endunless

                    @foreach ($days as $day)
                        @include('partials.cal-day', ['day' => $day])
                    @endforeach
                </div>
            </div>

            <p class="cal-legend" aria-hidden="true">
                <span><span class="cal-mark cal-mark--event"></span> the day it happened</span>
                <span><span class="cal-mark cal-mark--written"></span> the day it was written</span>
                <span><span class="cal-legend__gap"></span> the time between</span>
            </p>
        </section>

        {{-- ------------------------------------------------------------ year --}}
        <section class="cal-year" aria-labelledby="cal-year-title">
            <p class="cal-year__sub">
                {{ $plural($yearTotal, 'entry') }} in {{ $month->year }}, by the day each is about.
            </p>
            <div class="cal-year__months" data-cal-heatmap aria-describedby="cal-heat-hint">
                <p class="nd-sr-only" id="cal-heat-hint">Arrow keys move between days; Enter opens the day.</p>
                @for ($m = 0; $m < 12; $m++)
                    @php
                        $mStart = $yearStart->copy()->addMonths($m);
                        $mTotal = 0;
                        for ($d = $mStart->copy(); $d->isSameMonth($mStart); $d->addDay()) $mTotal += $heatmap[$d->toDateString()] ?? 0;
                        $lead = ($mStart->dayOfWeekIso - CalendarController::WEEK_START + 7) % 7 + 1;
                    @endphp
                    <section class="cal-mini @if ($mStart->isSameMonth($today)) is-now @endif">
                        <a class="cal-mini__link" href="{{ $monthUrl($mStart) }}">
                            <h3 class="cal-mini__name">{{ $mStart->format('F') }}</h3>
                            <span class="cal-mini__count">{{ $mTotal ? $plural($mTotal, 'entry') : 'none' }}</span>
                        </a>
                        <div class="cal-mini__grid" role="group" aria-label="{{ $mStart->format('F Y') }}">
                            @for ($d = $mStart->copy(); $d->isSameMonth($mStart); $d->addDay())
                                @php
                                    $key = $d->toDateString();
                                    $n = $heatmap[$key] ?? 0;
                                    $current = $key === $selected->toDateString();
                                @endphp
                                <a class="cal-heat @if ($d->day === 1) cal-c{{ $lead }} @endif @if ($d->equalTo($today)) is-today @endif"
                                   href="{{ $monthUrl($d, ['day' => $key]) }}" data-cal-heat="{{ $key }}" data-level="{{ $heatLevel($n) }}"
                                   tabindex="{{ $current ? '0' : '-1' }}"
                                   aria-label="{{ $d->format('l j F') }}: {{ $n ? $plural($n, 'entry') : 'no entries' }}@if ($d->equalTo($today)), today @endif"
                                   title="{{ $d->format('j M') }}: {{ $n ? $plural($n, 'entry') : 'no entries' }}"></a>
                            @endfor
                        </div>
                    </section>
                @endfor
            </div>
            <p class="cal-heat-legend">
                <span>Fewer</span>
                @for ($l = 0; $l <= 4; $l++)
                    <span class="cal-heat cal-heat--key" data-level="{{ $l }}" aria-hidden="true"></span>
                @endfor
                <span>More</span>
                <span class="nd-sr-only">Darker days have more entries; each day says how many.</span>
            </p>
        </section>
    </div>
</div>
@endsection
