@extends('layouts.app')

@push('head')
    {{-- Never kept for Back/Forward: this page can be holding a decrypted entry. --}}
    <meta name="turbo-cache-control" content="no-cache">
    @vite(['resources/css/diary.css', 'resources/js/diary-entry.js'])
@endpush

@section('content')
@php
    $locked = $entry->isLocked();
    $day = $entry->day();
    $ago = match (true) {
        $day->isToday() => 'Today',
        $day->isYesterday() => 'Yesterday',
        default => ucfirst($day->copy()->startOfDay()->diffForHumans(now()->startOfDay(), ['syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW, 'parts' => 1])),
    };
    // Which dialog to put back: the controller's marker for a wrong key, or the dialog's own
    // _modal when validate() bounced it (an empty algorithm, a short key).
    $reopen = session('dy_reopen') ?? (preg_match('/^(lock|unlock|delete):\d+$/', (string) old('_modal')) ? old('_modal') : null);
    $revealedContent = $revealedContent ?? null;
    $neighbourTitle = fn ($other) => $other->isLocked() ? 'a sealed entry' : $other->title;
@endphp

<div class="dy-read" id="dy-entry-root" data-entry-id="{{ $entry->id }}" data-locked="{{ $locked ? 'true' : 'false' }}">
    <nav class="dy-read__bar" aria-label="Entry">
        <a class="nd-btn nd-btn--ghost dy-read__back" href="{{ route('diary') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            All memories
        </a>

        <div class="dy-read__walk">
            @if ($older)
                <a class="nd-icon-btn" href="{{ route('diary.entry', $older->id) }}" data-dy-older aria-label="Older: {{ $neighbourTitle($older) }}" title="Older: {{ $neighbourTitle($older) }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
            @else
                <span class="nd-icon-btn dy-read__edge" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
            @endif
            @if ($newer)
                <a class="nd-icon-btn" href="{{ route('diary.entry', $newer->id) }}" data-dy-newer aria-label="Newer: {{ $neighbourTitle($newer) }}" title="Newer: {{ $neighbourTitle($newer) }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            @else
                <span class="nd-icon-btn dy-read__edge" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
            @endif
        </div>

        @if ($canWrite)
            <div class="dy-read__actions">
                @if ($locked)
                    <button type="button" class="nd-btn nd-btn--sm" data-dy-open="unlock">
                        <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
                        Unseal
                    </button>
                @else
                    <button type="button" class="nd-btn nd-btn--sm" data-dy-edit>
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        Edit
                    </button>
                    <button type="button" class="nd-btn nd-btn--sm" data-dy-open="lock">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        Seal
                    </button>
                @endif
                <button type="button" class="nd-icon-btn nd-icon-btn--danger" data-dy-open="delete" aria-label="Delete this memory" title="Delete this memory">
                    <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                </button>
            </div>
        @endif
    </nav>

    @if (session('success'))
        <div class="nd-banner nd-banner--success dy-banner" role="status">
            <i class="fa-solid fa-check" aria-hidden="true"></i>
            <p class="nd-banner__text">{{ session('success') }}</p>
        </div>
    @endif

    @if ($errors->any() && !$reopen && !old('_modal') && !$errors->has('key'))
        <div class="nd-banner nd-banner--error dy-banner" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <ul class="nd-banner__text">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <article class="dy-sheet @if ($locked) dy-sheet--sealed @endif" data-morph-target="dy-entry-{{ $entry->id }}" data-dy-sheet>
        <header class="dy-sheet__head">
            <p class="dy-sheet__date">
                <time datetime="{{ $day->format('Y-m-d') }}">{{ $day->format('l, j F Y') }}</time>
                <span>{{ $ago }}</span>
            </p>
            <h1 class="dy-sheet__title nd-written" dir="auto">{{ $entry->title }}</h1>
        </header>

        @if ($locked)
            {{-- Each sealed entry has its own frame: revealEntry() answers with this whole page
                 (a 200, never a redirect, so the plaintext never touches the session) and Turbo
                 swaps in only the frame whose id matches. --}}
            <turbo-frame id="dy-reveal-frame-{{ $entry->id }}" data-dy-reveal-frame>
                @if ($revealedContent !== null)
                    <div class="dy-revealed">
                        @include('partials.diary-entry-body', ['content' => $revealedContent, 'unseal' => true])
                        <div class="dy-revealed__foot">
                            <p class="nd-hint">Open on this screen only. Nothing was written back, and this page is never kept for Back.</p>
                            <button type="button" class="nd-btn" data-dy-reseal>
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                Seal it again
                            </button>
                        </div>
                    </div>
                @else
                    @include('partials.diary-reveal-form', ['pristine' => false])
                @endif
            </turbo-frame>

            {{-- OUTSIDE the frame, so a reveal never swaps it away. Sealing it again, and leaving
                 the page, put this copy back: a return always starts at the seal. --}}
            <template data-dy-reveal-template>
                @include('partials.diary-reveal-form', ['pristine' => true])
            </template>
        @else
            @include('partials.diary-entry-body', ['content' => $entry->content, 'unseal' => session('dy_unsealed') === $entry->id])
        @endif
    </article>

    <template data-dy-wax-template>@include('partials.diary-wax')</template>

    <dialog class="dy-lightbox" data-dy-lightbox aria-label="Photo">
        <button type="button" class="nd-icon-btn dy-lightbox__close" data-dy-close aria-label="Close photo"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        <img alt="" data-dy-lightbox-img>
    </dialog>

    @if ($canWrite)
        @include('partials.diary-dialogs')
        @unless ($locked)
            @include('partials.diary-flow', ['flowEntry' => $entry])
        @endunless
    @endif

    @php
        $entryData = [
            'id' => $entry->id,
            'locked' => $locked,
            'diaryUrl' => route('diary'),
            'context' => $reopen,
            'errors' => $reopen ? $errors->getMessages() : [],
        ];
    @endphp
    <script type="application/json" id="dy-entry-data">@json($entryData)</script>
</div>
@endsection
