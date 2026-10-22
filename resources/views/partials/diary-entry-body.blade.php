{{-- The readable part of an entry: what it held that day, then the words. Rendered for an
     open entry, and inside a sealed entry's frame once its key has opened it ($unseal plays
     the decrypt on arrival). --}}
@php
    $mood = $entry->moodDetails();
    $paragraphs = preg_split('/\R{2,}/u', trim($content));
    $words = count(preg_split('/\s+/u', trim($content), -1, PREG_SPLIT_NO_EMPTY));
    $unseal = $unseal ?? false;
@endphp

@if ($mood || $entry->track || $entry->people->isNotEmpty())
    <div class="dy-sheet__meta">
        @if ($mood)
            <span class="dy-mood" data-mood="{{ $entry->mood }}"><i class="fa-solid {{ $mood['icon'] }}" aria-hidden="true"></i>{{ $mood['label'] }}</span>
        @endif

        @if ($entry->track)
            <button type="button" class="dy-song" data-player-play="{{ json_encode($entry->track->playerPayload()) }}" aria-label="Play {{ $entry->track->title }}, the song of that day">
                <span class="dy-song__icon" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                <span class="dy-song__title" dir="auto">{{ $entry->track->title }}</span>
                @if ($entry->track->artist_name)
                    <span class="dy-song__artist" dir="auto">{{ $entry->track->artist_name }}</span>
                @endif
            </button>
        @endif

        @foreach ($entry->people as $person)
            <a class="nd-chip" href="{{ route('diary', ['person' => $person->name]) }}" title="Every memory with {{ $person->name }}">
                <i class="fa-regular fa-user" aria-hidden="true"></i><span dir="auto">{{ $person->name }}</span>
            </a>
        @endforeach
    </div>
@endif

@if ($entry->photos->isNotEmpty())
    <ul class="dy-gallery dy-gallery--{{ min($entry->photos->count(), 4) }}" role="list" aria-label="Photos from that day">
        @foreach ($entry->photos as $photo)
            <li>
                @if ($photo->protection === 'none')
                    <button type="button" class="dy-gallery__item" data-dy-photo="{{ route('photo.view', $photo->id) }}" aria-label="Open photo {{ $loop->iteration }} of {{ $loop->count }}">
                        <img src="{{ route('photo.thumbnail', $photo->id) }}" alt="" loading="lazy">
                    </button>
                @else
                    {{-- Protected since it was attached: its key belongs to the Photos page. --}}
                    <a class="dy-gallery__item dy-gallery__item--locked" href="{{ route('photos') }}" title="This photo is protected. Open it from the Photos page.">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <span>Protected photo</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
@endif

<div class="dy-text nd-written" data-dy-text @if ($unseal) data-dy-unseal @endif>
    @foreach ($paragraphs as $paragraph)
        <p dir="auto">{!! nl2br(e($paragraph), false) !!}</p>
    @endforeach
</div>

<p class="dy-sheet__stats">
    {{ number_format($words) }} {{ Str::plural('word', $words) }}, about {{ max(1, (int) ceil($words / 200)) }} min to read
    @if ($entry->event_date && !$entry->event_date->isSameDay($entry->created_at))
        <span>Written {{ $entry->created_at->format('j F Y') }}</span>
    @endif
</p>
