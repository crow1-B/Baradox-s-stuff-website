@php
    /**
     * The grid and its empty states. The Videos page and an album's page render exactly
     * this, so the two never drift apart.
     *
     * $context is 'all' or 'album' and only changes the words: an album with nothing in it
     * is a different situation from having registered no videos at all.
     */
    $context = $context ?? 'all';
    $total = $videos->count();
@endphp

@if ($videos->isEmpty())
    <div class="vd-empty mt-8">
        <span class="vd-empty__icon"><i class="fa-regular fa-file-video" aria-hidden="true"></i></span>
        @if ($context === 'album')
            <h2 class="text-base">This album is empty</h2>
            <p class="vd-muted max-w-sm text-sm">
                Nothing has been put in “{{ $album->name }}” yet. An album is picked when a video is
                registered, over on the Videos page.
            </p>
            <a class="vd-btn mt-2" href="{{ route('videos') }}">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                All videos
            </a>
        @else
            <h2 class="text-base">No videos yet</h2>
            <p class="vd-muted max-w-sm text-sm">
                Videos aren't uploaded here — copy the file onto the drive through Windows, then
                register it by name and it shows up in this grid.
            </p>
            <button type="button" class="vd-btn mt-2" data-vd-open-dialog="register">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                Register a video
            </button>
        @endif
    </div>
@else
    <div class="vd-toolbar mt-6">
        <p class="vd-count">{{ $total }} {{ Str::plural('video', $total) }}</p>
    </div>

    <ul class="vd-grid mt-4" role="list" data-vd-grid>
        @foreach ($videos as $video)
            @include('partials.vd-card', ['video' => $video, 'favoriteAlbumId' => $favoriteAlbumId, 'missingIds' => $missingIds])
        @endforeach
    </ul>
@endif
