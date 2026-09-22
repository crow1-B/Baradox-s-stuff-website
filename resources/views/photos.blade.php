@extends('layouts.app')

@push('head')
    @vite(['resources/css/photos.css', 'resources/js/photos.js'])
    {{-- The lightbox can hold a decrypted photo, so this page must never be restored from a
         Turbo snapshot. Same reason as Diary. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
<div class="ph" id="ph-root">
    <div class="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <h1>Photos</h1>
                <p class="ph-muted">Everything on the drive, grouped however you like.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="ph-btn" data-ph-open-dialog="album">
                    <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                    New album
                </button>
                <button type="button" class="ph-btn ph-btn--primary" data-ph-open-dialog="upload">
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    Upload photos
                </button>
            </div>
        </header>

        @include('partials.ph-banners')

        {{-- Albums ------------------------------------------------------- --}}
        <section class="mt-8" aria-labelledby="ph-albums-heading">
            <div class="flex items-end justify-between gap-3">
                <h2 class="text-base" id="ph-albums-heading">Albums</h2>
                <p class="ph-faint text-sm">{{ $albums->count() }} {{ Str::plural('album', $albums->count()) }}</p>
            </div>

            @if ($albums->isEmpty())
                <p class="ph-muted mt-3 text-sm">No albums yet — make one and you can drop photos into it from the grid below.</p>
            @else
                <ul class="ph-albums mt-3" role="list">
                    @foreach ($albums as $entry)
                        @php
                            $count = (int) ($albumCounts[$entry->id] ?? 0);
                            $coverId = $albumCovers[$entry->id] ?? null;
                        @endphp
                        <li class="ph-album">
                            @if ($coverId)
                                <img class="ph-album__cover"
                                     src="{{ route('photo.thumbnail', $coverId) }}"
                                     alt=""
                                     width="300" height="225"
                                     loading="lazy"
                                     decoding="async">
                            @else
                                {{-- Either the album is empty or everything in it is protected.
                                     A pixelated smear or a padlock would say nothing about the
                                     album, so both land on the placeholder. --}}
                                <span class="ph-album__cover ph-album__cover--blank" aria-hidden="true">
                                    <i class="fa-regular fa-images"></i>
                                </span>
                            @endif

                            <a class="ph-album__link ph-album__body" href="{{ route('albums.show', $entry->id) }}">
                                <span class="ph-album__name">
                                    @if ($entry->is_system)
                                        <i class="fa-solid fa-star" aria-hidden="true"></i>
                                    @endif
                                    <span>{{ $entry->name }}</span>
                                </span>
                                <span class="ph-album__count">{{ $count }} {{ Str::plural('photo', $count) }}</span>
                            </a>

                            @unless ($entry->is_system)
                                <span class="ph-album__manage">
                                    <button type="button" class="ph-btn ph-btn--sm ph-btn--icon"
                                            data-ph-album-action="rename" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
                                            aria-label="Rename {{ $entry->name }}">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="ph-btn ph-btn--sm ph-btn--icon ph-danger"
                                            data-ph-album-action="delete" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
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
        <section class="mt-9" aria-labelledby="ph-grid-heading">
            <h2 class="text-base" id="ph-grid-heading">All photos</h2>
            @include('partials.ph-board', ['context' => 'all'])
        </section>
    </div>

    @include('partials.ph-dialogs', ['manageAlbums' => true, 'album' => null])

    @php
        $phData = [
            'photos' => $photoData,
            'albumId' => null,
            'urls' => [
                'photo' => url('/photos'),
                'album' => url('/albums'),
                'upload' => route('photosPage'),
            ],
        ];
    @endphp
    <script type="application/json" id="ph-data">@json($phData)</script>
</div>
@endsection
