@extends('layouts.app')

@section('surface', 'nd')

@push('head')
    {{-- Don't keep a Back/Forward snapshot of this page: its search can hold anything typed. --}}
    <meta name="turbo-cache-control" content="no-cache">
    @vite(['resources/css/diary.css', 'resources/js/diary.js'])
@endpush

@section('content')
@php
    $grouped = $entries->groupBy(fn ($entry) => $entry->day()->format('Y-m'));
    $first = $entries->last()?->day();

    // Client-side filtering reads this. A sealed entry contributes its title and day and
    // nothing else: no text to match, no people to filter by.
    $entryData = $entries->mapWithKeys(fn ($entry) => [$entry->id => [
        'title' => $entry->title,
        'content' => $entry->isLocked() ? null : $entry->content,
        'people' => $entry->isLocked() ? [] : $entry->people->pluck('name')->values(),
    ]]);
@endphp

<div class="dy" id="dy-root">
    <header class="dy-head">
        <div>
            <h1 class="dy-head__title nd-written">Diary</h1>
            <p class="dy-head__sub">
                @if ($entries->isEmpty())
                    Nothing kept here yet.
                @else
                    {{ $entries->count() }} {{ Str::plural('memory', $entries->count()) }}, kept since {{ $first->format('F Y') }}.
                @endif
            </p>
        </div>
        @if ($canWrite)
            <button type="button" class="nd-btn nd-btn--primary nd-btn--lg" data-dy-write>
                <i class="fa-solid fa-feather" aria-hidden="true"></i>
                Close the day
            </button>
        @endif
    </header>

    @if (session('success'))
        <div class="nd-banner nd-banner--success dy-banner" role="status">
            <i class="fa-solid fa-check" aria-hidden="true"></i>
            <p class="nd-banner__text">{{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->any() && !old('_modal'))
        <div class="nd-banner nd-banner--error dy-banner" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <ul class="nd-banner__text">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($entries->isEmpty())
        <div class="dy-empty">
            <p class="dy-empty__line nd-written">The first page is the hardest one.</p>
            <p class="nd-hint">Each entry is about one day: its mood, its photos, the song that was playing, and whatever you want to say about it. Any of them can be sealed afterwards with a key only you hold.</p>
            @if ($canWrite)
                <button type="button" class="nd-btn nd-btn--primary" data-dy-write>
                    <i class="fa-solid fa-feather" aria-hidden="true"></i>
                    Close today
                </button>
            @endif
        </div>
    @else
        {{-- Filtering is client-side over the rendered cards. Nothing here asks the server for
             anything, so a sealed entry is never decrypted to answer a search. --}}
        <div class="dy-toolbar">
            <div class="dy-search">
                <i class="fa-solid fa-magnifying-glass dy-search__icon" aria-hidden="true"></i>
                <input type="search" class="nd-input dy-search__input" id="dy-search" placeholder="Search memories"
                       aria-label="Search memories" aria-describedby="dy-search-scope" autocomplete="off" dir="auto"
                       data-dy-search data-page-search>
            </div>
            <p class="dy-count" data-dy-count aria-live="polite">{{ $entries->count() }} {{ Str::plural('memory', $entries->count()) }}</p>
        </div>

        {{-- Saying this out loud matters: a search box that quietly skipped sealed entries would
             misrepresent what the diary holds. --}}
        <p class="dy-scope" id="dy-search-scope">
            <i class="fa-solid fa-lock" aria-hidden="true"></i>
            Searches every title, and the words of open entries. Sealed entries are stored as
            ciphertext, so only their titles can match, and they list no people to filter by.
        </p>

        <div class="dy-filter" data-dy-filter hidden>
            <span>Showing memories with</span>
            <span class="nd-chip nd-chip--active"><i class="fa-regular fa-user" aria-hidden="true"></i><span data-dy-filter-name dir="auto"></span></span>
            <button type="button" class="nd-btn nd-btn--sm nd-btn--ghost" data-dy-clear-person>
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                Show everyone
            </button>
        </div>

        @foreach ($grouped as $month => $monthEntries)
            <section class="dy-month" data-dy-group aria-labelledby="dy-month-{{ $month }}">
                <h2 class="dy-month__title" id="dy-month-{{ $month }}">
                    <span class="nd-written">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F') }}</span>
                    <span class="dy-month__year">{{ substr($month, 0, 4) }}</span>
                    <span class="dy-month__count" data-dy-group-count>{{ $monthEntries->count() }}</span>
                </h2>

                <ol class="dy-cards" role="list">
                    @foreach ($monthEntries as $entry)
                        @php
                            $locked = $entry->isLocked();
                            $day = $entry->day();
                            $mood = $locked ? null : $entry->moodDetails();
                        @endphp
                        <li class="dy-card @if ($locked) dy-card--sealed @endif" data-dy-entry data-entry-id="{{ $entry->id }}">
                            <time class="dy-card__day" datetime="{{ $day->format('Y-m-d') }}">
                                <span class="dy-card__num">{{ $day->format('j') }}</span>
                                <span class="dy-card__dow">{{ $day->format('D') }}</span>
                            </time>

                            <div class="dy-card__body">
                                <h3 class="dy-card__title nd-written">
                                    {{-- The title is the link; ::after stretches it over the card,
                                         so the people chips below stay buttons of their own. --}}
                                    <a class="dy-card__link" href="{{ route('diary.entry', $entry->id) }}" data-morph="dy-entry-{{ $entry->id }}" dir="auto">{{ $entry->title }}</a>
                                </h3>

                                @if ($locked)
                                    <p class="dy-card__sealed"><i class="fa-solid fa-lock" aria-hidden="true"></i> Sealed</p>
                                @else
                                    @if ($mood || $entry->track || $entry->photos->isNotEmpty() || !$entry->event_date)
                                        <p class="dy-card__meta">
                                            @if ($mood)
                                                <span class="dy-mood dy-mood--quiet" data-mood="{{ $entry->mood }}"><i class="fa-solid {{ $mood['icon'] }}" aria-hidden="true"></i>{{ $mood['label'] }}</span>
                                            @endif
                                            @if ($entry->track)
                                                <span class="dy-card__fact" dir="auto"><i class="fa-solid fa-music" aria-hidden="true"></i>{{ $entry->track->title }}</span>
                                            @endif
                                            @if ($entry->photos->isNotEmpty())
                                                <span class="dy-card__fact"><i class="fa-regular fa-image" aria-hidden="true"></i>{{ $entry->photos->count() }}</span>
                                            @endif
                                            @unless ($entry->event_date)
                                                <span class="dy-card__fact">written that day</span>
                                            @endunless
                                        </p>
                                    @endif

                                    <p class="dy-card__snippet nd-written" dir="auto">{{ Str::limit($entry->content, 260) }}</p>

                                    @if ($entry->people->isNotEmpty())
                                        <div class="dy-card__people">
                                            @foreach ($entry->people as $person)
                                                <button type="button" class="nd-chip" data-dy-person="{{ $person->name }}">
                                                    <i class="fa-regular fa-user" aria-hidden="true"></i><span dir="auto">{{ $person->name }}</span>
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if ($locked)
                                @include('partials.diary-wax', ['class' => 'dy-card__wax'])
                            @elseif ($entry->photos->isNotEmpty())
                                @php $cover = $entry->photos->firstWhere('protection', 'none'); @endphp
                                @if ($cover)
                                    <img class="dy-card__cover" src="{{ route('photo.thumbnail', $cover->id) }}" alt="" loading="lazy">
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach

        <div class="dy-empty" data-dy-no-matches hidden>
            <p class="dy-empty__line nd-written">Nothing matches.</p>
            <p class="nd-hint" data-dy-no-matches-note></p>
            <button type="button" class="nd-btn" data-dy-clear>Clear the search</button>
        </div>
    @endif

    {{-- Holds a CSRF token on the page for anything that posts without a form of its own. --}}
    <form hidden aria-hidden="true">@csrf</form>

    @if ($canWrite)
        @include('partials.diary-flow', ['flowEntry' => null])
    @endif

    <script type="application/json" id="dy-data">@json(['entries' => $entryData])</script>
</div>
@endsection
