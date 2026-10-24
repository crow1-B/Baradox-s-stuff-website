@extends('layouts.app')

@push('head')
    @vite(['resources/css/home.css', 'resources/js/home.js'])
@endpush

@section('content')
@php
    // Stored names look like "Diary Page." — the list already says these are pages.
    $pageLabel = fn ($visit) => preg_replace('/\s*Page\.?$/', '', $visit->page_name) ?: $visit->page_name;

    // [from hour, greeting, line]. home.js re-picks by the browser's clock, the same clock
    // the light follows, so the words and the room always agree.
    // Guest can't write, so the lines that ask for writing get a quieter version.
    $greetings = [
        [0, "Still up, {$name}?", $canWrite ? 'The house is asleep. Write it down before it fades.' : 'The house is asleep.'],
        [5, "Good morning, {$name}.", 'Early light. Nothing has asked for you yet.'],
        [8, "Good morning, {$name}.", 'The day is still open.'],
        [12, "Good afternoon, {$name}.", 'Half the day behind you.'],
        [17, "Good evening, {$name}.", 'The light is going amber.'],
        [20, "Good evening, {$name}.", $canWrite ? 'The desk is lit. A good hour to close the day.' : 'The desk is lit.'],
    ];
    $hour = (int) $now->format('G');
    [, $greeting, $line] = collect($greetings)->last(fn ($g) => $g[0] <= $hour);

    $yearsAgo = function ($date) use ($now) {
        $years = $now->year - $date->year;
        return $years === 1 ? 'A year ago' : "{$years} years ago";
    };
@endphp

<div class="home">
    <header class="home-hero">
        <p class="home-hero__date">
            <time datetime="{{ $now->toDateString() }}">{{ $now->format('l, j F Y') }}</time>
            <time class="home-hero__clock" datetime="{{ $now->toIso8601String() }}" data-home-clock>{{ $now->format('H:i') }}</time>
        </p>
        <h1 class="home-hero__greeting nd-written" data-home-greeting>{{ $greeting }}</h1>
        <p class="home-hero__line" data-home-line>{{ $line }}</p>
        <script type="application/json" id="home-greetings">@json($greetings)</script>

        <div class="home-hero__actions">
            @if ($todaysEntry)
                <a class="nd-btn nd-btn--lg" href="{{ route('diary.entry', $todaysEntry->id) }}"
                   data-morph="dy-entry-{{ $todaysEntry->id }}">
                    <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                    Read today’s entry
                </a>
            @elseif ($canWrite)
                <a class="nd-btn nd-btn--primary nd-btn--lg" href="{{ route('diary', ['write' => 1]) }}">
                    <i class="fa-solid fa-feather" aria-hidden="true"></i>
                    Close the day
                </a>
                <span class="home-hero__hint">or press <kbd class="nd-kbd">W</kbd> from anywhere</span>
            @endif
        </div>
    </header>

    {{-- On this day: the reason to open this page at all. --}}
    <section class="home-otd" aria-labelledby="home-otd-title">
        <h2 class="home-section__title" id="home-otd-title">On this day</h2>

        @if ($onThisDay['entries']->isEmpty() && $onThisDay['photos']->isEmpty())
            <p class="home-quiet">
                Nothing from {{ $now->format('j F') }} in earlier years yet.
                @if ($canWrite)
                    Whatever you write today will be waiting here next year.
                @endif
            </p>
        @else
            <ol class="home-otd__list" role="list">
                @foreach ($onThisDay['entries'] as $entry)
                    @php $day = $entry->day(); @endphp
                    <li>
                        @if ($entry->isLocked())
                            {{-- A sealed entry admits it exists and nothing else: no title, no mood. --}}
                            <a class="home-slip home-slip--sealed" href="{{ route('diary.entry', $entry->id) }}"
                               data-morph="dy-entry-{{ $entry->id }}">
                                <span class="home-slip__when">{{ $yearsAgo($day) }}, {{ $day->year }}</span>
                                <span class="home-slip__title"><i class="fa-solid fa-lock" aria-hidden="true"></i> A sealed entry</span>
                                <span class="home-slip__text">Its key opens it in the diary.</span>
                            </a>
                        @else
                            @php $mood = $entry->moodDetails(); @endphp
                            <a class="home-slip" href="{{ route('diary.entry', $entry->id) }}"
                               data-morph="dy-entry-{{ $entry->id }}">
                                <span class="home-slip__when">
                                    {{ $yearsAgo($day) }}, {{ $day->year }}
                                    @if ($mood)
                                        <span class="home-slip__mood" data-mood="{{ $entry->mood }}"><i class="fa-solid {{ $mood['icon'] }}" aria-hidden="true"></i> {{ $mood['label'] }}</span>
                                    @endif
                                </span>
                                <span class="home-slip__title nd-written" dir="auto">{{ $entry->title }}</span>
                                <span class="home-slip__text nd-written" dir="auto">{{ Str::limit($entry->content, 220) }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ol>

            @if ($onThisDay['photos']->isNotEmpty())
                <ul class="home-otd__photos" role="list" aria-label="Photos from this day">
                    @foreach ($onThisDay['photos'] as $photo)
                        <li>
                            <a class="home-otd__photo" href="{{ route('photos', ['photo' => $photo->id]) }}" title="{{ $yearsAgo($photo->created_at) }}">
                                <img src="{{ $photo->thumbnailUrl() }}" alt="Photo from {{ $photo->created_at->format('j F Y') }}" loading="lazy">
                                <span>{{ $photo->created_at->year }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <div class="home-grid">
        <section class="home-activity" aria-labelledby="home-activity-title">
            <h2 class="home-section__title" id="home-activity-title">Recent activity</h2>

            @if ($activity->isEmpty())
                <p class="home-quiet">Nothing added yet. Songs, photos, entries and notes show up here as you add them.</p>
            @else
                <ol class="home-timeline" role="list">
                    @foreach ($activity as $event)
                        <li class="home-timeline__item">
                            <span class="home-timeline__icon" aria-hidden="true"><i class="fa-solid {{ $event['icon'] }}"></i></span>
                            <a class="home-timeline__text" href="{{ $event['url'] }}" dir="auto">{{ $event['text'] }}</a>
                            <time class="home-timeline__when" datetime="{{ $event['when']->toIso8601String() }}" title="{{ $event['when']->format('j M Y, H:i') }}">{{ $event['when']->diffForHumans(short: true) }}</time>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <aside class="home-side">
            <section class="home-song nd-card" aria-labelledby="home-song-title">
                <h2 class="home-section__title" id="home-song-title">
                    {{ $trackReason === 'played' ? 'Most played today' : 'Newest song' }}
                </h2>
                @if ($track)
                    <div class="home-song__row">
                        {{-- Same data-player-play hook as the Music page: player.js starts it in the dock. --}}
                        <button type="button" class="home-song__play" data-player-play="{{ json_encode($track->playerPayload()) }}" aria-label="Play {{ $track->title }}">
                            <i class="fa-solid fa-play" aria-hidden="true"></i>
                        </button>
                        <p class="home-song__meta">
                            <span class="home-song__title" dir="auto">{{ $track->title }}</span>
                            @if ($track->artist_name)
                                <span class="home-song__artist" dir="auto">{{ $track->artist_name }}</span>
                            @endif
                        </p>
                        @if ($track->duration)
                            <span class="home-song__length">{{ $track->durationForHumans() }}</span>
                        @endif
                    </div>
                @else
                    <p class="home-quiet">No songs yet. <a class="nd-link" href="{{ route('music') }}">Add one on the Music page</a>.</p>
                @endif
            </section>

            <section class="home-visits nd-card" aria-labelledby="home-visits-title">
                <div class="home-visits__head">
                    <h2 class="home-section__title" id="home-visits-title">Where you’ve been</h2>
                    @if ($recentVisits->isNotEmpty())
                        <a class="nd-link home-visits__all" href="{{ route('visits.all') }}">Full history</a>
                    @endif
                </div>

                @if ($recentVisits->isEmpty())
                    <p class="home-quiet">Pages you open show up here.</p>
                @else
                    <ul class="home-visits__list" role="list">
                        @foreach ($recentVisits as $visit)
                            <li class="home-visit">
                                <a class="home-visit__page" href="{{ url('/' . ltrim($visit->path, '/')) }}">{{ $pageLabel($visit) }}</a>
                                <time class="home-visit__when" datetime="{{ $visit->created_at->toIso8601String() }}" title="{{ $visit->created_at->format('j M Y, H:i') }}">{{ $visit->created_at->diffForHumans(short: true) }}</time>
                                @if ($canWrite)
                                    <form method="POST" action="/visits/{{ $visit->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="nd-icon-btn nd-icon-btn--sm nd-icon-btn--danger" aria-label="Remove {{ $pageLabel($visit) }} from history" title="Remove from history">
                                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </aside>
    </div>

    <footer class="home-foot">Made by Baradox</footer>
</div>

@if ($showAllVisits ?? false)
    <dialog class="nd-dialog" id="home-history" aria-labelledby="home-history-title" data-home-history data-close-url="{{ route('homepage') }}">
        <div class="nd-dialog__head">
            <div>
                <h2 class="nd-dialog__title" id="home-history-title">Every page you’ve opened</h2>
                <p class="nd-dialog__sub">{{ $allVisits->count() }} {{ Str::plural('visit', $allVisits->count()) }}, newest first</p>
            </div>
            <a class="nd-icon-btn" href="{{ route('homepage') }}" aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></a>
        </div>
        <div class="nd-dialog__body">
            @if ($allVisits->isEmpty())
                <p class="home-quiet">The history is empty.</p>
            @else
                <ul class="home-history" role="list">
                    @foreach ($allVisits as $visit)
                        <li class="home-visit">
                            <span class="home-visit__page">{{ $visit->page_name }}</span>
                            <time class="home-visit__when" datetime="{{ $visit->created_at->toIso8601String() }}">{{ $visit->created_at->format('j M Y, H:i') }}</time>
                            @if ($canWrite)
                                <form method="POST" action="/visits/{{ $visit->id }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="nd-icon-btn nd-icon-btn--sm nd-icon-btn--danger" aria-label="Remove this visit">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @if ($canWrite && $allVisits->isNotEmpty())
            <div class="nd-dialog__foot">
                <form method="POST" action="/visits">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="nd-btn nd-btn--ghost"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Clear the whole history</button>
                </form>
            </div>
        @endif
    </dialog>
@endif
@endsection
