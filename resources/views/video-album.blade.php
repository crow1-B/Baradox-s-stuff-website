@extends('layouts.app')

@push('head')
    @vite(['resources/css/videos.css', 'resources/js/videos.js'])
    {{-- Same reason as the Videos page: the watch dialog can hold a gated stream. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
@php $count = $videos->count(); @endphp

<div class="nd-page nd-page--wide vd" id="vd-root">
    <a class="nd-btn nd-btn--ghost nd-btn--sm vd-back" href="{{ route('videos') }}">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        All videos
    </a>

    <header class="nd-page__head">
        <div>
            <h1 class="nd-page__title">
                @if ($album->is_system)
                    <i class="fa-solid fa-star vd-star" aria-hidden="true"></i>
                @endif
                {{ $album->name }}
            </h1>
            <p class="nd-page__sub">{{ $count }} {{ Str::plural('video', $count) }} in this album.</p>
        </div>
        @if ($canWrite)
            <div class="nd-page__actions">
                {{-- Favorite is a system album and is not in the register dialog's picker, so
                     on that page the button must not promise to register into it. --}}
                <button type="button" class="nd-btn nd-btn--primary" data-vd-open-dialog="register">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    {{ $album->is_system ? 'Register a video' : 'Register into this album' }}
                </button>
            </div>
        @endif
    </header>

    @include('partials.vd-banners')

    <div class="vd-section">
        @include('partials.vd-board', ['context' => 'album'])
    </div>

    @include('partials.vd-dialogs', ['manageAlbums' => false])

    @php
        $vdData = [
            'videos' => $videoData,
            'albumId' => $album->id,
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
