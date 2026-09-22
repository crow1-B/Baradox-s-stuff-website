@extends('layouts.app')

@push('head')
    @vite(['resources/css/extra-notes.css', 'resources/js/extra-notes.js'])
@endpush

@section('content')
@php
    // Everything the page's JS needs, in one place. Filter state is mirrored into the query
    // string as it changes, so a reload lands on the same view.
    $data = [
        'base' => url('/extra_notes'),
        'messagesUrl' => route('extra-notes.messages'),
        'hasMore' => $hasMore,
        'oldestId' => $notes->first()?->id,
        'total' => $totalCount,
        'q' => $query,
        'tags' => $activeTags,
        'maxTags' => $maxTags,
        'maxBulk' => config('extra_notes.max_bulk'),
        'labels' => collect($tags)->map(fn ($tag) => $tag['label']),
    ];

    $filtering = $query !== '' || $activeTags !== [];
@endphp

<div class="xn" id="xn-root">
    <div class="xn-page">

        <header class="xn-head">
            <div class="xn-head__row">
                <div class="xn-head__title">
                    <h1>Extra Notes</h1>
                    <p class="xn-muted">
                        Everything you are offloading. Reusable snippets you will look up again
                        stay in your notes files, not here.
                    </p>
                </div>

                <button type="button" class="xn-btn" data-xn-select-toggle aria-pressed="false">
                    <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                    Select
                </button>
            </div>

            <div class="xn-tools">
                {{-- Server-side search, unlike Music's client-side filter: the stream is paged
                     backwards, so filtering only the loaded window would quietly miss most of
                     the notes and look like they had been lost. --}}
                <div class="xn-search">
                    <i class="fa-solid fa-magnifying-glass xn-search__icon" aria-hidden="true"></i>
                    <input type="search"
                           class="xn-input xn-search__input"
                           id="xn-search"
                           value="{{ $query }}"
                           placeholder="Search every message"
                           aria-label="Search every message"
                           autocomplete="off"
                           dir="auto"
                           data-xn-search>
                </div>

                <div class="xn-tagfilter" role="group" aria-label="Filter by tag">
                    @foreach ($tags as $key => $tag)
                        <button type="button"
                                class="xn-tagbtn @if (in_array($key, $activeTags, true)) xn-tagbtn--on @endif"
                                data-xn-tagfilter="{{ $key }}"
                                aria-pressed="{{ in_array($key, $activeTags, true) ? 'true' : 'false' }}"
                                title="{{ $tag['label'] }}">
                            <i class="{{ $tag['icon'] }}" aria-hidden="true"></i>
                            <span class="xn-sr">{{ $tag['label'] }}</span>
                        </button>
                    @endforeach

                    <button type="button" class="xn-btn xn-btn--sm" data-xn-clear @unless ($filtering) hidden @endunless>
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                        Clear
                    </button>
                </div>

                <p class="xn-hint xn-hint--keys">
                    <kbd>C</kbd> write · <kbd>/</kbd> search · <kbd>Enter</kbd> send · <kbd>Shift</kbd>+<kbd>Enter</kbd> newline
                </p>
            </div>

            <div class="xn-flash" id="xn-flash" role="status" aria-live="polite"></div>
        </header>

        @include('partials.xn-pinned', ['pinned' => $pinned, 'tags' => $tags])

        <div class="xn-stream" data-xn-scroll>
            {{-- Sentinel for backward pagination. It sits above the list so that reaching the
                 top of the scroll container is what asks for older messages. --}}
            <div class="xn-top" data-xn-top>
                <p class="xn-top__loading" data-xn-top-loading hidden>
                    <i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i>
                    Loading older messages…
                </p>
                {{-- The third empty state: not "nothing here" and not "nothing matches", but
                     "you have reached the start". --}}
                <p class="xn-top__done" data-xn-top-done hidden>That is the beginning.</p>
            </div>

            <div class="xn-list" id="xn-stream-list" data-xn-list>
                @foreach ($notes as $note)
                    @include('partials.xn-message', ['note' => $note, 'formatter' => $formatter, 'tags' => $tags])
                @endforeach
            </div>

            {{-- Two of the three empty states. They are different situations and saying so
                 saves a moment of "where did everything go?". --}}
            <div class="xn-empty" data-xn-empty="none" @unless ($totalCount === 0) hidden @endunless>
                <span class="xn-empty__icon"><i class="fa-solid fa-comment-dots" aria-hidden="true"></i></span>
                <h2>Nothing captured yet</h2>
                <p class="xn-muted">Type below and press Enter. No title, no folder, no decision — that is the point.</p>
            </div>

            <div class="xn-empty" data-xn-empty="no-matches" @unless ($filtering && $notes->isEmpty()) hidden @endunless>
                <span class="xn-empty__icon"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i></span>
                <h2>Nothing matches</h2>
                <p class="xn-muted" data-xn-no-matches-note>No message matches the current search and tags.</p>
                <button type="button" class="xn-btn" data-xn-clear>Clear the filter</button>
            </div>
        </div>

        <div class="xn-dock">
            {{-- Selection action bar. Appears only with something selected; there is no
                 "select all" on purpose — with backward pagination it would be ambiguous
                 whether it meant the loaded messages or every message. --}}
            <div class="xn-selbar" data-xn-selbar hidden>
                <span class="xn-selbar__count" data-xn-selcount aria-live="polite">0 selected</span>
                <button type="button" class="xn-btn xn-btn--sm" data-xn-bulk="tags">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    Tag
                </button>
                <button type="button" class="xn-btn xn-btn--sm xn-btn--danger" data-xn-bulk="delete">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    Delete
                </button>
                <button type="button" class="xn-btn xn-btn--sm xn-btn--ghost" data-xn-selcancel>Cancel</button>
            </div>

            <form class="xn-composer" method="POST" action="{{ route('extra-notes.store') }}" data-xn-composer>
                @csrf

                <div class="xn-composer__tags" role="group" aria-label="Tags for this message">
                    @foreach ($tags as $key => $tag)
                        <input type="checkbox" name="tags[]" value="{{ $key }}" id="xn-ct-{{ $key }}" data-xn-composer-tag>
                        <label for="xn-ct-{{ $key }}" title="{{ $tag['label'] }}">
                            <i class="{{ $tag['icon'] }}" aria-hidden="true"></i>
                            <span class="xn-sr">{{ $tag['label'] }}</span>
                        </label>
                    @endforeach
                </div>

                <textarea class="xn-input xn-composer__input"
                          name="content"
                          id="xn-composer-input"
                          rows="1"
                          dir="auto"
                          maxlength="{{ $maxLength }}"
                          placeholder="Write it down…"
                          aria-label="Write a message"
                          data-xn-input></textarea>

                <button type="submit" class="xn-send" data-xn-send aria-label="Send">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    </div>

    {{-- Shared forms ---------------------------------------------------------
         One per action, living outside the stream. A form inside a message card would be torn
         out from under itself the moment a turbo-stream replaced that card. JS points these at
         the right message and submits them; Turbo intercepts and renders the stream back. --}}
    <form method="POST" data-xn-pin-form hidden>
        @csrf
        @method('PATCH')
    </form>

    {{-- Edit ---------------------------------------------------------------- --}}
    <dialog class="xn-dialog" id="xn-edit-dialog" aria-labelledby="xn-edit-title">
        <form method="POST" action="" data-xn-edit-form>
            @csrf
            @method('PATCH')

            <div class="xn-dialog__head">
                <h2 class="xn-dialog__title" id="xn-edit-title">Edit message</h2>
                <button type="button" class="xn-act" data-xn-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="xn-dialog__body">
                <textarea class="xn-input xn-input--area" name="content" id="xn-edit-content" rows="8" dir="auto"
                          maxlength="{{ $maxLength }}" aria-label="Message"></textarea>
                <p class="xn-hint">Editing marks the message as edited. Pinning and tagging do not.</p>
            </div>

            <div class="xn-dialog__foot">
                <button type="button" class="xn-btn" data-xn-close>Cancel</button>
                <button type="submit" class="xn-btn xn-btn--primary">Save changes</button>
            </div>
        </form>
    </dialog>

    {{-- Tags on one message -------------------------------------------------- --}}
    <dialog class="xn-dialog xn-dialog--sm" id="xn-tags-dialog" aria-labelledby="xn-tags-title">
        <form method="POST" action="" data-xn-tags-form>
            @csrf
            @method('PATCH')

            <div class="xn-dialog__head">
                <h2 class="xn-dialog__title" id="xn-tags-title">Tags</h2>
                <button type="button" class="xn-act" data-xn-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="xn-dialog__body">
                <div class="xn-tagpick">
                    @foreach ($tags as $key => $tag)
                        <input type="checkbox" name="tags[]" value="{{ $key }}" id="xn-tp-{{ $key }}" data-xn-tagpick>
                        <label for="xn-tp-{{ $key }}">
                            <i class="{{ $tag['icon'] }}" aria-hidden="true"></i>
                            <span>{{ $tag['label'] }}</span>
                        </label>
                    @endforeach
                </div>
                <p class="xn-hint">Up to {{ $maxTags }} per message. The list is fixed — tags are not something to invent mid-thought.</p>
            </div>

            <div class="xn-dialog__foot">
                <button type="button" class="xn-btn" data-xn-close>Cancel</button>
                <button type="submit" class="xn-btn xn-btn--primary">Save tags</button>
            </div>
        </form>
    </dialog>

    {{-- Delete one ----------------------------------------------------------- --}}
    <dialog class="xn-dialog xn-dialog--sm" id="xn-delete-dialog" aria-labelledby="xn-delete-title">
        <form method="POST" action="" data-xn-delete-form>
            @csrf
            @method('DELETE')

            <div class="xn-dialog__head">
                <h2 class="xn-dialog__title" id="xn-delete-title">Delete this message?</h2>
                <button type="button" class="xn-act" data-xn-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="xn-dialog__body">
                <p class="xn-preview" data-xn-delete-preview dir="auto"></p>
                <p class="xn-hint">This cannot be undone.</p>
            </div>

            <div class="xn-dialog__foot">
                <button type="button" class="xn-btn" data-xn-close>Cancel</button>
                <button type="submit" class="xn-btn xn-btn--danger">Delete</button>
            </div>
        </form>
    </dialog>

    {{-- Bulk delete ---------------------------------------------------------- --}}
    <dialog class="xn-dialog xn-dialog--sm" id="xn-bulk-delete-dialog" aria-labelledby="xn-bulk-delete-title">
        <form method="POST" action="{{ route('extra-notes.bulk-destroy') }}" data-xn-bulk-delete-form>
            @csrf
            @method('DELETE')
            <div data-xn-ids></div>

            <div class="xn-dialog__head">
                {{-- Deleting 23 messages is a different action from deleting one, so the
                     dialog says which. --}}
                <h2 class="xn-dialog__title" id="xn-bulk-delete-title" data-xn-bulk-delete-title>Delete messages?</h2>
                <button type="button" class="xn-act" data-xn-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="xn-dialog__body">
                <p class="xn-muted">This cannot be undone.</p>
            </div>

            <div class="xn-dialog__foot">
                <button type="button" class="xn-btn" data-xn-close>Cancel</button>
                <button type="submit" class="xn-btn xn-btn--danger" data-xn-bulk-delete-submit>Delete</button>
            </div>
        </form>
    </dialog>

    {{-- Bulk tag ------------------------------------------------------------- --}}
    <dialog class="xn-dialog xn-dialog--sm" id="xn-bulk-tags-dialog" aria-labelledby="xn-bulk-tags-title">
        <form method="POST" action="{{ route('extra-notes.bulk-tags') }}" data-xn-bulk-tags-form>
            @csrf
            @method('PATCH')
            <div data-xn-ids></div>

            <div class="xn-dialog__head">
                <h2 class="xn-dialog__title" id="xn-bulk-tags-title" data-xn-bulk-tags-title>Tag messages</h2>
                <button type="button" class="xn-act" data-xn-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="xn-dialog__body">
                <div class="xn-seg" role="group" aria-label="Apply or remove">
                    <input type="radio" name="mode" value="add" id="xn-bt-add" checked>
                    <label for="xn-bt-add">Apply</label>
                    <input type="radio" name="mode" value="remove" id="xn-bt-remove">
                    <label for="xn-bt-remove">Remove</label>
                </div>

                <div class="xn-tagpick mt-4" role="radiogroup" aria-label="Which tag">
                    @foreach ($tags as $key => $tag)
                        <input type="radio" name="tag" value="{{ $key }}" id="xn-bt-{{ $key }}" @if ($loop->first) checked @endif>
                        <label for="xn-bt-{{ $key }}">
                            <i class="{{ $tag['icon'] }}" aria-hidden="true"></i>
                            <span>{{ $tag['label'] }}</span>
                        </label>
                    @endforeach
                </div>

                <p class="xn-hint">A message already carrying {{ $maxTags }} tags is left alone rather than having one of them dropped for you.</p>
            </div>

            <div class="xn-dialog__foot">
                <button type="button" class="xn-btn" data-xn-close>Cancel</button>
                <button type="submit" class="xn-btn xn-btn--primary">Apply</button>
            </div>
        </form>
    </dialog>

    <script type="application/json" id="xn-data">@json($data)</script>
</div>
@endsection
