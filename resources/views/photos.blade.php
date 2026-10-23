@extends('layouts.app')

@section('surface', 'nd')

@push('head')
    @vite(['resources/css/photos.css', 'resources/js/photos.js'])
    {{-- The lightbox can hold a decrypted photo, so this page must never be restored from a
         Turbo snapshot. Same reason as Diary. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
<div class="nd-page nd-page--wide ph" id="ph-root">
    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">Photos</h1>
            <p class="nd-page__sub">
                @if ($photos->isEmpty())
                    Nothing on the drive yet.
                @else
                    {{ $photos->count() }} {{ Str::plural('photograph', $photos->count()) }}, kept on the drive.
                @endif
            </p>
        </div>
        @if ($canWrite)
            <div class="nd-page__actions">
                <button type="button" class="nd-btn" data-ph-open-dialog="album">
                    <i class="fa-solid fa-folder-plus" aria-hidden="true"></i>
                    New album
                </button>
                <button type="button" class="nd-btn nd-btn--primary" data-ph-open-dialog="upload">
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    Upload photos
                </button>
            </div>
        @endif
    </header>

    @include('partials.ph-banners')

    {{-- Albums ------------------------------------------------------- --}}
    <section class="ph-section" aria-labelledby="ph-albums-heading">
        <div class="ph-section__head">
            <h2 class="ph-section__title" id="ph-albums-heading">Albums</h2>
            <p class="nd-count">{{ $albums->count() }} {{ Str::plural('album', $albums->count()) }}</p>
        </div>

        @if ($albums->isEmpty())
            <p class="nd-hint ph-section__note">
                No albums yet.@if ($canWrite) Make one and you can drop photos into it from the grid below.@endif
            </p>
        @else
            <ul class="ph-albums" role="list">
                @foreach ($albums as $entry)
                    @php
                        $count = (int) ($albumCounts[$entry->id] ?? 0);
                        $coverId = $albumCovers[$entry->id] ?? null;
                    @endphp
                    <li class="ph-album">
                        @if ($coverId)
                            {{-- The cover morphs into the same photo's tile on the album's page. --}}
                            <img class="ph-album__cover"
                                 src="{{ route('photo.thumbnail', $coverId) }}"
                                 alt=""
                                 width="300" height="225"
                                 loading="lazy"
                                 decoding="async"
                                 data-ph-album-cover>
                        @else
                            {{-- Either the album is empty or everything in it is protected.
                                 A pixelated smear or a padlock would say nothing about the
                                 album, so both land on the placeholder. --}}
                            <span class="ph-album__cover ph-album__cover--blank" aria-hidden="true">
                                <i class="fa-regular fa-images"></i>
                            </span>
                        @endif

                        <a class="ph-album__link ph-album__body" href="{{ route('albums.show', $entry->id) }}"
                           @if ($coverId) data-morph="ph-album-{{ $entry->id }}" data-morph-from="[data-ph-album-cover]" @endif>
                            <span class="ph-album__name">
                                @if ($entry->is_system)
                                    <i class="fa-solid fa-star" aria-hidden="true"></i>
                                @endif
                                <span>{{ $entry->name }}</span>
                            </span>
                            <span class="ph-album__count">{{ $count }} {{ Str::plural('photo', $count) }}</span>
                        </a>

                        @if ($canWrite && !$entry->is_system)
                            <span class="ph-album__manage">
                                <button type="button" class="ph-act"
                                        data-ph-album-action="rename" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
                                        aria-label="Rename {{ $entry->name }}">
                                    <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                </button>
                                <button type="button" class="ph-act ph-act--danger"
                                        data-ph-album-action="delete" data-id="{{ $entry->id }}" data-name="{{ $entry->name }}"
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
    <section class="ph-section" aria-labelledby="ph-grid-heading">
        <div class="ph-section__head">
            <h2 class="ph-section__title" id="ph-grid-heading">All photos</h2>
        </div>
        @include('partials.ph-board', ['context' => 'all'])
    </section>

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
