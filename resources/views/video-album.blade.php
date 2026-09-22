@extends('layouts.app')

@push('head')
    @vite(['resources/css/videos.css', 'resources/js/videos.js'])
    {{-- Same reason as the Videos page: the watch dialog can hold a gated stream. --}}
    <meta name="turbo-cache-control" content="no-cache">
@endpush

@section('content')
@php $count = $videos->count(); @endphp

<div class="vd" id="vd-root">
    <div class="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 sm:pt-10">

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-col gap-1.5">
                <a class="vd-muted inline-flex items-center gap-2 text-sm no-underline hover:underline" href="{{ route('videos') }}">
                    <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                    All videos
                </a>
                <h1>
                    @if ($album->is_system)
                        <i class="fa-solid fa-star vd-star" aria-hidden="true"></i>
                    @endif
                    {{ $album->name }}
                </h1>
                <p class="vd-muted">{{ $count }} {{ Str::plural('video', $count) }} in this album.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                {{-- Favorite is a system album and is not in the register dialog's picker, so
                     on that page the button must not promise to register into it. --}}
                <button type="button" class="vd-btn vd-btn--primary" data-vd-open-dialog="register">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    {{ $album->is_system ? 'Register a video' : 'Register into this album' }}
                </button>
            </div>
        </header>

        @include('partials.vd-banners')

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
