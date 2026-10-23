@extends('layouts.app')

@push('head')
    @vite(['resources/css/videos.css', 'resources/js/videos.js'])
    {{-- The watch dialog can be holding a gated stream (the key rides in the src), so this
         page must never come back from a Turbo snapshot. Same reason as Photos and Diary. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
<div class="nd-page nd-page--wide vd" id="vd-root">
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Videos</h1>
            <p class="nd-page__sub">
                @if ($videos->isEmpty())
                    Nothing registered off the drive yet.
                @else
                    {{ $videos->count() }} {{ Str::plural('video', $videos->count()) }}, played off the drive without leaving the page.
                @endif
            </p>
        </div>
        @if ($canWrite)
            <div class="nd-page__actions">
                <button type="button" class="nd-btn" data-vd-open-dialog="album">
                    <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                    New album
                </button>
                <button type="button" class="nd-btn nd-btn--primary" data-vd-open-dialog="register">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Register a video
                </button>
            </div>
        @endif
    </header>

    @include('partials.vd-banners')

    {{-- Albums ------------------------------------------------------- --}}
    <section class="vd-section" aria-labelledby="vd-albums-heading">
        <div class="vd-section__head">
            <h2 class="vd-section__title" id="vd-albums-heading">Albums</h2>
            <p class="nd-count">{{ $albums->count() }} {{ Str::plural('album', $albums->count()) }}</p>
        </div>

        @if ($albums->isEmpty())
            <p class="nd-hint vd-section__note">
                No albums yet.@if ($canWrite) Make one and it can be picked when a video is registered.@endif
            </p>
        @else
            <ul class="vd-albums" role="list">
                @foreach ($albums as $entry)
                    @php
                        $count = (int) ($albumCounts[$entry->id] ?? 0);
                        $cover = $albumCovers[$entry->id] ?? null;
                    @endphp
                    <li class="vd-album">
                        @if ($cover)
                            <img class="vd-album__cover"
                                 src="{{ $cover->posterUrl() }}"
                                 alt=""
                                 width="640" height="360"
                                 loading="lazy"
                                 decoding="async"
                                 data-vd-poster>
                        @else
                            {{-- Either the album is empty or everything in it is gated. A
                                 pixelated smear says nothing about the album, so both land
                                 on the placeholder — same rule as a photo album's cover. --}}
                            <span class="vd-album__cover vd-album__cover--blank" aria-hidden="true">
                                <i class="fa-regular fa-file-video"></i>
                            </span>
                        @endif

                        <a class="vd-album__link vd-album__body" href="{{ route('video-albums.show', $entry->id) }}">
                            <span class="vd-album__name">
                                @if ($entry->is_system)
                                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                                @endif
                                <span>{{ $entry->name }}</span>
                            </span>
                            <span class="vd-album__count">{{ $count }} {{ Str::plural('video', $count) }}</span>
                        </a>

                        @if ($canWrite && !$entry->is_system)
                            <span class="vd-album__manage">
                                <button type="button" class="vd-act"
                                        data-vd-album-action="rename" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
                                        aria-label="Rename {{ $entry->name }}">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="vd-act vd-act--danger"
                                        data-vd-album-action="delete" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
                                        aria-label="Delete {{ $entry->name }}">
                                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                </button>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- The grid ----------------------------------------------------- --}}
    <section class="vd-section" aria-labelledby="vd-grid-heading">
        <div class="vd-section__head">
            <h2 class="vd-section__title" id="vd-grid-heading">All videos</h2>
            @if ($videos->isNotEmpty())
                <p class="nd-count">{{ $videos->count() }} {{ Str::plural('video', $videos->count()) }}</p>
            @endif
        </div>
        @include('partials.vd-board', ['context' => 'all'])
    </section>

    @include('partials.vd-dialogs', ['manageAlbums' => true, 'album' => null])

    @php
        $vdData = [
            'videos' => $videoData,
            'albumId' => null,
            'highlightId' => $highlightId,
            'urls' => [
                'video' => url('/videos'),
                'album' => url('/video-albums'),
            ],
        ];
    @endphp
    <script type="application/json" id="vd-data">@json($vdData)</script>
</div>
@endsection
