@php
    $context = $context ?? 'all';
    $total = $videos->count();
@endphp

@if ($videos->isEmpty())
    <div class="nd-empty nd-empty--block">
        @if ($context === 'album')
            <p class="nd-empty__line">This album is empty.</p>
            <p>
                Nothing has been put in “{{ $album->name }}” yet.@if ($canWrite) An album is picked when a video is
                registered, over on the Videos page.@endif
            </p>
            <a class="nd-btn" href="{{ route('videos') }}">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                All videos
            </a>
        @else
            <p class="nd-empty__line">No reels on the shelf.</p>
            @if ($canWrite)
                <p>
                    Videos aren't uploaded here — copy the file onto the drive through Windows, then
                    register it by name and it shows up in this grid.
                </p>
                <button type="button" class="nd-btn nd-btn--primary" data-vd-open-dialog="register">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    Register a video
                </button>
            @endif
        @endif
    </div>
@else
    <ul class="vd-grid" role="list" data-vd-grid>
        @foreach ($videos as $video)
            @include('partials.vd-card', ['video' => $video, 'favoriteAlbumId' => $favoriteAlbumId, 'missingIds' => $missingIds])
        @endforeach
    </ul>
@endif
