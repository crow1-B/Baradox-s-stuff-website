@extends('layouts.app')

@push('head')
    @vite(['resources/css/videos.css', 'resources/js/videos.js'])
    {{-- The watch dialog can be holding a gated stream (the key rides in the src), so this
         page must never come back from a Turbo snapshot. Same reason as Photos and Diary. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
<div class="vd" id="vd-root">
    <div class="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <h1>Videos</h1>
                <p class="vd-muted">Everything registered off the drive, playable without leaving the page.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="vd-btn" data-vd-open-dialog="album">
                    <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                    New album
                </button>
                <button type="button" class="vd-btn vd-btn--primary" data-vd-open-dialog="register">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Register a video
                </button>
            </div>
        </header>

        @include('partials.vd-banners')

        {{-- Albums ------------------------------------------------------- --}}
        <section class="mt-8" aria-labelledby="vd-albums-heading">
            <div class="flex items-end justify-between gap-3">
                <h2 class="text-base" id="vd-albums-heading">Albums</h2>
                <p class="vd-faint text-sm">{{ $albums->count() }} {{ Str::plural('album', $albums->count()) }}</p>
            </div>

            @if ($albums->isEmpty())
                <p class="vd-muted mt-3 text-sm">No albums yet — make one and it can be picked when a video is registered.</p>
            @else
                <ul class="vd-albums mt-3" role="list">
                    @foreach ($albums as $entry)
                        @php
                            $count = (int) ($albumCounts[$entry->id] ?? 0);
                            $coverId = $albumCovers[$entry->id] ?? null;
                        @endphp
                        <li class="vd-album">
                            @if ($coverId)
                                <img class="vd-album__cover"
                                     src="{{ route('video.poster', $coverId) }}"
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

                            @unless ($entry->is_system)
                                <span class="vd-album__manage">
                                    <button type="button" class="vd-btn vd-btn--sm vd-btn--icon"
                                            data-vd-album-action="rename" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
                                            aria-label="Rename {{ $entry->name }}">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="vd-btn vd-btn--sm vd-btn--icon vd-danger"
                                            data-vd-album-action="delete" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
                                            aria-label="Delete {{ $entry->name }}">
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </span>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- The grid ----------------------------------------------------- --}}
        <section class="mt-9" aria-labelledby="vd-grid-heading">
            <h2 class="text-base" id="vd-grid-heading">All videos</h2>
            @include('partials.vd-board', ['context' => 'all'])
        </section>
    </div>

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
