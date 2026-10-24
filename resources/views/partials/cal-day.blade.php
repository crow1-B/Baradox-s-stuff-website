@php
    // One day's panel: the selected day beside the grid, and on a phone one section of the
    // agenda. Every row carries data-layer, so the filters hide it the same way everywhere.
    $date = $day['date'];
    $key = $date->toDateString();
    $isToday = $date->equalTo($today);
    $inMonth = $date->isSameMonth($month);
    $dayLabel = fn ($d) => $d->isSameYear($today) ? $d->format('j M') : $d->format('j M Y');
    $after = function ($gap) {
        $n = abs($gap);
        $days = $n . ' ' . Str::plural('day', $n);
        return $gap > 0 ? "{$days} later" : "{$days} ahead";
    };
    $photoCount = count($day['photos']) + count($day['lockedPhotos']);
    // Which layers have something here; calendar.js keeps is-blank right as filters change.
    $present = array_keys(array_filter([
        'diary' => $day['entries'] || $day['written'],
        'projects' => (bool) $day['projects'],
        'photos' => $photoCount > 0,
        'videos' => (bool) $day['videos'],
        'tracks' => (bool) $day['tracks'],
        'listening' => (bool) $day['listening'],
    ]));
    $blank = !array_intersect($present, $activeLayers);
@endphp
<section class="cal-day @if ($key === $selected->toDateString()) is-selected @endif @if (!$inMonth) is-out @endif @if ($isToday) is-today @endif @if ($blank) is-blank @endif"
         id="cal-day-{{ $key }}" data-cal-panel="{{ $key }}" aria-labelledby="cal-day-{{ $key }}-title">
    <h3 class="cal-day__title" id="cal-day-{{ $key }}-title">
        <span class="cal-day__weekday">{{ $date->format('l') }}</span>
        <span class="cal-day__date">{{ $date->format('j F') }}@if (!$date->isSameYear($today)) {{ $date->year }}@endif</span>
        @if ($isToday)
            <span class="nd-badge cal-day__today">Today</span>
        @endif
    </h3>

    <ul class="cal-items" role="list">
        @foreach ($day['entries'] as $entry)
            <li data-layer="diary">
                @if ($entry['locked'])
                    {{-- A sealed entry admits it exists and nothing else. --}}
                    <a class="cal-item" href="{{ $entry['url'] }}" data-morph="dy-entry-{{ $entry['id'] }}">
                        <span class="cal-item__icon" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                        <span class="cal-item__main">
                            <span class="cal-item__title">A sealed entry</span>
                            <span class="cal-item__meta">Its key opens it in the diary.</span>
                        </span>
                    </a>
                @else
                    <a class="cal-item" href="{{ $entry['url'] }}" data-morph="dy-entry-{{ $entry['id'] }}" data-cal-entry="{{ $entry['id'] }}"
                       @if ($entry['mood']) data-mood="{{ $entry['mood'] }}" @endif>
                        <span class="cal-item__icon" aria-hidden="true"><span class="cal-mark cal-mark--event"></span></span>
                        <span class="cal-item__main">
                            <span class="cal-item__title nd-written" dir="auto">{{ $entry['title'] }}</span>
                            <span class="cal-item__meta">
                                @if ($entry['moodLabel']){{ $entry['moodLabel'] }} · @endif
                                @if ($entry['gap'] === 0)
                                    Written the same day
                                @else
                                    Written {{ $dayLabel($entry['written']) }}, {{ $after($entry['gap']) }}
                                @endif
                            </span>
                        </span>
                    </a>
                @endif
            </li>
        @endforeach

        @foreach ($day['written'] as $entry)
            <li data-layer="diary">
                <a class="cal-item" href="{{ $entry['url'] }}" data-morph="dy-entry-{{ $entry['id'] }}" data-cal-entry="{{ $entry['id'] }}">
                    <span class="cal-item__icon" aria-hidden="true"><span class="cal-mark cal-mark--written"></span></span>
                    <span class="cal-item__main">
                        <span class="cal-item__title nd-written" dir="auto">{{ $entry['title'] }}</span>
                        <span class="cal-item__meta">Written this day, about {{ $dayLabel($entry['event']) }}</span>
                    </span>
                </a>
            </li>
        @endforeach

        @foreach ($day['projects'] as $project)
            <li data-layer="projects">
                <a class="cal-item" href="{{ $project['url'] }}">
                    <span class="cal-item__icon" aria-hidden="true"><span class="nd-dot nd-dot--{{ $project['status'] }}"></span></span>
                    <span class="cal-item__main">
                        <span class="cal-item__title" dir="auto">{{ $project['title'] }}</span>
                        <span class="cal-item__meta">
                            @if ($project['edge'] === 'start')
                                {{ $project['status'] === 'future' ? 'Planned to start' : 'Started' }}
                            @else
                                {{ $project['status'] === 'done' ? 'Finished' : 'Due to finish' }}
                            @endif
                            · {{ ucfirst($project['statusLabel']) }}
                        </span>
                    </span>
                </a>
            </li>
        @endforeach

        @if ($photoCount)
            <li data-layer="photos" class="cal-photos">
                <p class="cal-photos__head">
                    <i class="fa-solid fa-image" aria-hidden="true"></i>
                    <a class="nd-link" href="{{ route('photos') }}">{{ $photoCount }} {{ Str::plural('photo', $photoCount) }} added</a>
                </p>
                @php
                    // Plain photos first, then padlocks; a padlock is a link to the key prompt
                    // and nothing more — no thumbnail of any kind.
                    $tiles = array_merge(
                        array_map(fn ($p) => $p + ['locked' => false], $day['photos']),
                        array_map(fn ($p) => $p + ['locked' => true], $day['lockedPhotos']),
                    );
                    $more = count($tiles) - $photoLimit;
                @endphp
                <ul class="cal-photos__grid" role="list">
                    @foreach (array_slice($tiles, 0, $photoLimit) as $photo)
                        <li>
                            @if ($photo['locked'])
                                <a class="cal-photos__tile cal-photos__tile--locked" href="{{ $photo['url'] }}">
                                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                    <span class="nd-sr-only">Locked photo added {{ $date->format('j F Y') }}, asks for its key</span>
                                </a>
                            @else
                                <a class="cal-photos__tile" href="{{ $photo['url'] }}">
                                    <img class="cal-photos__thumb" src="{{ $photo['thumb'] }}" alt="Photo added {{ $date->format('j F Y') }}" loading="lazy" decoding="async">
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($more > 0 || $day['lockedPhotos'])
                    <p class="cal-item__meta">
                        @if ($more > 0)
                            and {{ $more }} more
                        @endif
                        @if ($day['lockedPhotos'])
                            <span class="cal-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ count($day['lockedPhotos']) }} locked</span>
                        @endif
                    </p>
                @endif
            </li>
        @endif

        @foreach ($day['videos'] as $video)
            <li data-layer="videos">
                <a class="cal-item" href="{{ $video['url'] }}">
                    <span class="cal-item__icon" aria-hidden="true"><i class="fa-solid {{ $video['locked'] ? 'fa-lock' : 'fa-film' }}"></i></span>
                    <span class="cal-item__main">
                        <span class="cal-item__title" dir="auto">{{ $video['locked'] ? 'A locked video' : $video['title'] }}</span>
                        <span class="cal-item__meta">Video registered</span>
                    </span>
                </a>
            </li>
        @endforeach

        @foreach ($day['tracks'] as $track)
            <li data-layer="tracks">
                <button type="button" class="cal-item" data-player-play="{{ json_encode($track['play']) }}">
                    <span class="cal-item__icon" aria-hidden="true"><i class="fa-solid fa-music"></i></span>
                    <span class="cal-item__main">
                        <span class="cal-item__title" dir="auto">{{ $track['title'] }}</span>
                        <span class="cal-item__meta">Song added @if ($track['artist'])· <span dir="auto">{{ $track['artist'] }}</span>@endif</span>
                    </span>
                    <span class="nd-sr-only">, play</span>
                </button>
            </li>
        @endforeach

        @if ($day['listening'])
            @php $listening = $day['listening']; @endphp
            <li data-layer="listening" class="cal-listen">
                <p class="cal-listen__head">
                    <i class="fa-solid fa-headphones" aria-hidden="true"></i>
                    {{ $listening['plays'] }} {{ Str::plural('play', $listening['plays']) }} of {{ $listening['songs'] }} {{ Str::plural('song', $listening['songs']) }}
                </p>
                <ol class="cal-listen__top" role="list">
                    @foreach ($listening['top'] as $top)
                        <li>
                            <button type="button" class="cal-item cal-item--compact" data-player-play="{{ json_encode($top['play']) }}">
                                <span class="cal-item__icon" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
                                <span class="cal-item__main">
                                    <span class="cal-item__title" dir="auto">{{ $top['title'] }}</span>
                                    <span class="cal-item__meta">{{ $top['count'] }}×</span>
                                </span>
                                <span class="nd-sr-only">, play</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </li>
        @endif
    </ul>

    <p class="cal-day__blank">Nothing on this day.</p>
</section>
