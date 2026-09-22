@extends('layouts.app')

@push('head')
    @vite(['resources/css/photos.css', 'resources/js/photos.js'])
    {{-- Same reason as the Photos page: the lightbox can hold a decrypted photo. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
@php $count = $photos->count(); @endphp

<div class="ph" id="ph-root">
    <div class="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <a class="ph-muted inline-flex items-center gap-2 text-sm no-underline hover:underline" href="{{ route('photos') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    All photos
                </a>
                <h1>
                    @if ($album->is_system)
                        <i class="fa-solid fa-star ph-star" aria-hidden="true"></i>
                    @endif
                    {{ $album->name }}
                </h1>
                <p class="ph-muted">{{ $count }} {{ Str::plural('photo', $count) }} in this album.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="ph-btn ph-btn--primary" data-ph-open-dialog="upload">
                    <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                    Upload into this album
                </button>
            </div>
        </header>

        @include('partials.ph-banners')

        @include('partials.ph-board', ['context' => 'album'])
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
