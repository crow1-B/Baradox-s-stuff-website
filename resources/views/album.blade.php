@extends('layouts.app')

@push('head')
    @vite(['resources/css/photos.css', 'resources/js/photos.js'])
    {{-- Same reason as the Photos page: the lightbox can hold a decrypted photo. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
@php
    $count = $photos->count();
    // The album card on the Photos page shows the newest unprotected photo; the album lists
    // newest first too, so this is the tile that cover morphs into.
    $morphId = $photos->firstWhere('protection', 'none')?->id;
@endphp

<div class="nd-page nd-page--wide ph" id="ph-root">
    <a class="nd-btn nd-btn--ghost nd-btn--sm ph-back" href="{{ route('photos') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        All photos
    </a>

    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">
                @if ($album->is_system)
                    <i class="fa-solid fa-star ph-star" aria-hidden="true"></i>
                @endif
                {{ $album->name }}
            </h1>
            <p class="nd-page__sub">{{ $count }} {{ Str::plural('photo', $count) }} in this album.</p>
        </div>
        @if ($canWrite)
            <div class="nd-page__actions">
                <button type="button" class="nd-btn nd-btn--primary" data-ph-open-dialog="upload">
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    Upload into this album
                </button>
            </div>
        @endif
    </header>

    @include('partials.ph-banners')

    <div class="ph-section">
        @include('partials.ph-board', ['context' => 'album', 'morphId' => $morphId])
    </div>

    @include('partials.ph-dialogs', ['manageAlbums' => false])

    @php
        $phData = [
            'photos' => $photoData,
            'albumId' => $album->id,
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
