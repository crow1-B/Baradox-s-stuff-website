{{-- "Close the day": the day, a mood, the day's photos, its song, then the words. One form;
     the steps are panels over it, so whichever step you leave from, everything submits
     together. $flowEntry is the entry being edited, or null to write a new one.

     Every value is filled in by diary-flow.js from #dy-flow-data (an edit's values, a draft
     kept on this device, or the old input of a submit that came back with errors), so
     there is one code path for all three. --}}
@php
    $editing = $flowEntry !== null;
    $steps = [
        'when' => 'The day',
        'mood' => 'Mood',
        'photos' => 'Photos',
        'song' => 'Song',
        'words' => 'Words',
    ];
@endphp

<dialog class="dy-flow" id="dy-flow" aria-labelledby="dy-flow-title" data-dy-flow data-step="when">
    <form method="POST" action="{{ $editing ? url('/diary/' . $flowEntry->id) : route('diaryPage') }}"
          enctype="multipart/form-data" novalidate data-dy-flow-form>
        @csrf
        @if ($editing)
            @method('PATCH')
        @endif
        <input type="hidden" name="_modal" value="{{ $editing ? 'edit:' . $flowEntry->id : 'create' }}">

        <header class="dy-flow__head">
            <button type="button" class="nd-icon-btn" data-dy-flow-close aria-label="Close. The draft stays on this device">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
            <h2 class="nd-sr-only" id="dy-flow-title">{{ $editing ? 'Edit this memory' : 'Close the day' }}</h2>

            <ol class="dy-steps" role="list">
                @foreach ($steps as $key => $label)
                    <li>
                        <button type="button" class="dy-steps__step" data-dy-step-to="{{ $key }}">
                            <span class="dy-steps__dot" aria-hidden="true"></span>
                            <span class="dy-steps__label">{{ $label }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <p class="dy-flow__save" data-dy-autosave aria-live="polite"></p>
        </header>

        <div class="dy-flow__stage">
            {{-- 1 · the day --}}
            <section class="dy-step" data-dy-step="when" aria-labelledby="dy-step-when">
                <div class="dy-step__inner">
                    <h3 class="dy-step__ask nd-written" id="dy-step-when">Which day are you closing?</h3>
                    <p class="dy-step__day nd-written" data-dy-date-label aria-live="polite"></p>
                    <div class="dy-step__row">
                        <button type="button" class="nd-chip" data-dy-date-shift="0">Today</button>
                        <button type="button" class="nd-chip" data-dy-date-shift="-1">Yesterday</button>
                        <label class="nd-sr-only" for="fl-event_date">The day this entry is about</label>
                        <input class="nd-input dy-step__date" type="date" id="fl-event_date" name="event_date" aria-describedby="fl-event_date-error">
                    </div>
                    <p class="nd-error" id="fl-event_date-error" data-dy-error hidden></p>
                </div>
            </section>

            {{-- 2 · mood --}}
            <section class="dy-step" data-dy-step="mood" aria-labelledby="dy-step-mood" hidden>
                <div class="dy-step__inner">
                    <h3 class="dy-step__ask nd-written" id="dy-step-mood">How did it feel?</h3>
                    <div class="dy-moods" role="radiogroup" aria-labelledby="dy-step-mood">
                        @foreach ($moods as $key => $mood)
                            <label class="dy-moods__item" data-mood="{{ $key }}">
                                <input type="radio" name="mood" value="{{ $key }}">
                                <i class="fa-solid {{ $mood['icon'] }}" aria-hidden="true"></i>
                                <span>{{ $mood['label'] }}</span>
                            </label>
                        @endforeach
                        <label class="dy-moods__item dy-moods__item--none">
                            <input type="radio" name="mood" value="">
                            <i class="fa-regular fa-circle" aria-hidden="true"></i>
                            <span>Rather not say</span>
                        </label>
                    </div>
                    <p class="nd-error" id="fl-mood-error" data-dy-error hidden></p>
                </div>
            </section>

            {{-- 3 · photos --}}
            <section class="dy-step" data-dy-step="photos" aria-labelledby="dy-step-photos" hidden>
                <div class="dy-step__inner dy-step__inner--wide">
                    <h3 class="dy-step__ask nd-written" id="dy-step-photos">Anything you saw?</h3>
                    <p class="dy-step__hint" data-dy-photos-note></p>

                    <div class="dy-ptiles" data-dy-photos role="group" aria-labelledby="dy-step-photos">
                        @foreach ($photos as $photo)
                            <label class="dy-ptile @if ($photo->protection === 'gated') dy-ptile--gated @endif" data-day="{{ $photo->created_at->toDateString() }}" data-photo-id="{{ $photo->id }}">
                                <input type="checkbox" name="photo_ids[]" value="{{ $photo->id }}" aria-label="Photo added {{ $photo->created_at->format('j F Y, H:i') }}">
                                @if ($photo->protection === 'encrypted')
                                    <span class="dy-ptile__locked"><i class="fa-solid fa-lock" aria-hidden="true"></i></span>
                                @else
                                    <img src="{{ $photo->thumbnailUrl() }}" alt="" loading="lazy">
                                @endif
                                <span class="dy-ptile__tick" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                            </label>
                        @endforeach
                    </div>

                    <div class="dy-step__row">
                        <button type="button" class="nd-btn nd-btn--sm" data-dy-photos-all aria-pressed="false" @if ($photos->isEmpty()) hidden @endif>
                            <i class="fa-regular fa-images" aria-hidden="true"></i>
                            <span data-dy-photos-all-label>Show other days too</span>
                        </button>
                        <label class="nd-btn nd-btn--sm dy-upload">
                            <i class="fa-solid fa-arrow-up-from-bracket" aria-hidden="true"></i>
                            Add from this device
                            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-dy-upload aria-describedby="fl-photos-hint">
                        </label>
                    </div>
                    <ul class="dy-uploads" data-dy-upload-list role="list" hidden></ul>
                    <p class="nd-hint" id="fl-photos-hint">New photos become real photos on the Photos page. jpg, png, webp or gif, up to 20 MB each. Files you add here aren’t kept in the draft.</p>
                    <p class="nd-error" id="fl-photo_ids-error" data-dy-error hidden></p>
                    <p class="nd-error" id="fl-photos-error" data-dy-error hidden></p>
                </div>
            </section>

            {{-- 4 · song --}}
            <section class="dy-step" data-dy-step="song" aria-labelledby="dy-step-song" hidden>
                <div class="dy-step__inner">
                    <h3 class="dy-step__ask nd-written" id="dy-step-song">And the song of the day?</h3>

                    <div class="dy-suggest" data-dy-suggest hidden>
                        <p class="dy-suggest__why" data-dy-suggest-why></p>
                        <div class="dy-suggest__row">
                            <span class="dy-suggest__art" aria-hidden="true"><i class="fa-solid fa-music"></i></span>
                            <p class="dy-suggest__meta">
                                <span class="dy-suggest__title" data-dy-suggest-title dir="auto"></span>
                                <span class="dy-suggest__artist" data-dy-suggest-artist dir="auto"></span>
                            </p>
                            <button type="button" class="nd-btn nd-btn--primary nd-btn--sm" data-dy-suggest-take>Make it the song</button>
                        </div>
                    </div>

                    @if ($tracks->isEmpty())
                        <p class="dy-step__hint">No songs yet. Add some on the Music page and they can be picked here.</p>
                    @else
                        <label class="nd-sr-only" for="fl-track-search">Find a song</label>
                        <input class="nd-input dy-tracks__search" type="search" id="fl-track-search" placeholder="Find a song" autocomplete="off" dir="auto" data-dy-track-search>
                    @endif

                    <div class="dy-tracks" role="radiogroup" aria-labelledby="dy-step-song" data-dy-tracks>
                        <label class="dy-tracks__item dy-tracks__item--none">
                            <input type="radio" name="track_id" value="">
                            <span class="dy-tracks__title">No song for this one</span>
                        </label>
                        @foreach ($tracks as $track)
                            <label class="dy-tracks__item" data-track-id="{{ $track->id }}" data-search="{{ $track->title }} {{ $track->artist_name }}">
                                <input type="radio" name="track_id" value="{{ $track->id }}">
                                <span class="dy-tracks__title" dir="auto">{{ $track->title }}</span>
                                <span class="dy-tracks__artist" dir="auto">{{ $track->artist_name }}</span>
                                <span class="dy-tracks__len">{{ $track->durationForHumans() }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p class="nd-error" id="fl-track_id-error" data-dy-error hidden></p>
                </div>
            </section>

            {{-- 5 · words: focus mode --}}
            <section class="dy-step dy-write" data-dy-step="words" aria-labelledby="dy-step-words" hidden>
                <h3 class="nd-sr-only" id="dy-step-words">Write it down</h3>
                <div class="dy-write__scroller" data-dy-scroller>
                    <div class="dy-write__page">
                        <label class="nd-sr-only" for="fl-title">Title</label>
                        <input class="dy-write__title nd-written" id="fl-title" name="title" type="text" maxlength="255"
                               autocomplete="off" dir="auto" aria-describedby="fl-title-error" data-dy-title>
                        <p class="nd-error" id="fl-title-error" data-dy-error hidden></p>

                        <label class="nd-sr-only" for="fl-content">The entry</label>
                        <textarea class="dy-write__text nd-written" id="fl-content" name="content" dir="auto" rows="1"
                                  placeholder="Start anywhere." aria-describedby="fl-content-error" data-dy-text-input></textarea>
                        <p class="nd-error" id="fl-content-error" data-dy-error hidden></p>

                        <div class="dy-write__people">
                            <label class="nd-label" for="fl-people"><i class="fa-regular fa-user" aria-hidden="true"></i> Who was there</label>
                            <input class="nd-input" id="fl-people" name="people" type="text" autocomplete="off" dir="auto"
                                   placeholder="Comma separated" aria-describedby="fl-people-error">
                            <p class="nd-error" id="fl-people-error" data-dy-error hidden></p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <footer class="dy-flow__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-dy-back>
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Back
            </button>
            <p class="dy-flow__count" data-dy-wordcount aria-live="off"></p>
            <div class="dy-flow__go">
                <button type="button" class="nd-btn nd-btn--ghost" data-dy-skip>Skip</button>
                <button type="button" class="nd-btn nd-btn--primary" data-dy-next>
                    Next
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <button type="submit" class="nd-btn nd-btn--primary" data-dy-submit hidden>
                    <i class="fa-solid fa-feather" aria-hidden="true"></i>
                    {{ $editing ? 'Save changes' : 'Close the day' }}
                </button>
            </div>
        </footer>
    </form>
</dialog>

@php
    $flowValues = $editing ? [
        'event_date' => $flowEntry->event_date?->format('Y-m-d'),
        'mood' => $flowEntry->mood,
        'photo_ids' => $flowEntry->photos->pluck('id')->values(),
        'track_id' => $flowEntry->track_id,
        'title' => $flowEntry->title,
        'content' => $flowEntry->content,
        'people' => $flowEntry->people->pluck('name')->join(', '),
    ] : null;

    $flowTracks = $tracks->mapWithKeys(fn ($track) => [$track->id => ['title' => $track->title, 'artist' => $track->artist_name]]);
    $flowData = [
        'mode' => $editing ? 'edit' : 'create',
        'entryId' => $flowEntry?->id,
        'user' => Auth::user()->id,
        'values' => $flowValues,
        'tracks' => $flowTracks,
        'topTracks' => $topTracks,
        // A submit that came back: reopen the flow with what was typed and where it failed.
        // key/algorithm can't be in old input (dontFlash), and are stripped here regardless.
        'context' => old('_modal'),
        'old' => collect(session()->getOldInput())->except(['_token', '_method', '_modal', 'key', 'algorithm']),
        'errors' => old('_modal') ? $errors->getMessages() : [],
        'saved' => session('dy_saved'),
    ];
@endphp
<script type="application/json" id="dy-flow-data">@json($flowData)</script>
