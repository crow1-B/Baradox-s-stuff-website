@extends('layouts.app')

@push('head')
    @vite('resources/css/home.css')
@endpush

@section('content')
@php
    // Stored names look like "Diary Page." — the card already says these are pages.
    $pageLabel = fn ($visit) => preg_replace('/\s*Page\.?$/', '', $visit->page_name) ?: $visit->page_name;
@endphp

<div class="home">
    <section class="home-hero" aria-labelledby="home-greeting">
        <div class="home-hero__words">
            <h1 class="home-hero__title" id="home-greeting">{{ $headline }}</h1>
            <p class="home-hero__sub">{{ $subline }}</p>
        </div>
        <p class="home-hero__time">
            <time datetime="{{ $now->toIso8601String() }}">
                <span class="home-hero__clock">{{ $now->format('H:i') }}</span>
                <span class="home-hero__date">{{ $now->format('l, j F Y') }}</span>
            </time>
        </p>
    </section>

    <div class="grid grid-cols-1 items-start gap-4 lg:grid-cols-3">
        <section class="home-card lg:col-span-2" aria-labelledby="home-visits-title">
            <div class="home-card__head">
                <h2 class="home-card__title" id="home-visits-title">Recently visited</h2>
                @if ($recentVisits->isNotEmpty())
                    <a class="home-link" href="{{ route('visits.all') }}">See full history</a>
                @endif
            </div>

            @if ($recentVisits->isEmpty())
                <p class="home-empty">No pages visited yet. Open any page from the sidebar and it will show up here.</p>
            @else
                <ul class="home-visits" role="list">
                    @foreach ($recentVisits as $visit)
                        <li class="home-visit">
                            <a class="home-visit__page" href="{{ url('/' . ltrim($visit->path, '/')) }}">{{ $pageLabel($visit) }}</a>
                            <time class="home-visit__when" datetime="{{ $visit->created_at->toIso8601String() }}" title="{{ $visit->created_at->format('j M Y, H:i') }}">{{ $visit->created_at->diffForHumans() }}</time>
                            <form method="POST" action="/visits/{{ $visit->id }}" class="home-visit__remove">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="home-icon-btn" aria-label="Remove {{ $pageLabel($visit) }} from history" title="Remove from history">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7 7 17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="home-card" aria-labelledby="home-song-title">
            <div class="home-card__head">
                <h2 class="home-card__title" id="home-song-title">Something to hear?</h2>
            </div>

            @if ($lastTrack)
                <div class="home-song">
                    <p class="home-song__title">{{ $lastTrack->title }}</p>
                    @if ($lastTrack->artist_name)
                        <p class="home-song__artist">{{ $lastTrack->artist_name }}</p>
                    @endif
                    <p class="home-song__meta">
                        <span>Added {{ $lastTrack->created_at->diffForHumans() }}</span>
                        @if ($lastTrack->duration)
                            <span>{{ intdiv($lastTrack->duration, 60) }}:{{ str_pad($lastTrack->duration % 60, 2, '0', STR_PAD_LEFT) }}</span>
                        @endif
                    </p>
                </div>
                {{-- Same data-player-play hook as the Music page: player.js starts it in the pill. --}}
                <button type="button" class="home-play" data-player-play="{{ json_encode($lastTrack->playerPayload()) }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.4-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/></svg>
                    Play
                </button>
            @else
                <p class="home-empty">No songs yet. <a class="home-link" href="{{ route('music') }}">Add one on the Music page</a> and it will be suggested here.</p>
            @endif
        </section>
    </div>
</div>

@if ($showAllVisits ?? false)
<div class="visits-overlay">
    <div class="visits-modal" role="dialog" aria-modal="true" aria-labelledby="visits-modal-title">
        <h3 id="visits-modal-title">All visited pages</h3>
        <form method="POST" action="/visits" class="home-overlay-clear">
            @csrf
            @method('DELETE')
            <button type="submit">delete all history</button>
        </form>
        <ul>
            @foreach ($allVisits as $visit)
                <li>
                    {{ $visit->page_name }} — {{ $visit->created_at->format('Y-m-d H:i') }}
                    <form method="POST" action="/visits/{{ $visit->id }}" class="home-overlay-row-form">
                        @csrf
                        @method('DELETE')
                        <button type="submit">delete</button>
                    </form>
                </li>
            @endforeach
        </ul>
        <a href="{{ route('homepage') }}" data-visits-close autofocus>close</a>
    </div>
</div>
@endif

@endsection
