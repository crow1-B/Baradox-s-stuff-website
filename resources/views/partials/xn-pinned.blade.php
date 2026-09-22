{{-- The pinned rail. Pinned messages surface here AND stay where they were written, so the
     stream never develops a hole. Rendered whole and swapped whole by turbo-stream `replace`,
     which is why the id is on the outermost element.

     Deliberately NOT filtered by the search box or the tag chips: pinning means "always where
     I can see it", and a filter taking that away would defeat it. Jumping to a message that
     the current filter hides is handled in JS, which says so rather than doing nothing. --}}
<section class="xn-pinned" id="xn-pinned" data-xn-pinned aria-label="Pinned messages" @if ($pinned->isEmpty()) hidden @endif>
    <div class="xn-pinned__head">
        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
        <span>Pinned</span>
        <span class="xn-pinned__count">{{ $pinned->count() }}</span>
        <button type="button" class="xn-act xn-pinned__toggle" data-xn-pinned-toggle aria-expanded="true" aria-controls="xn-pinned-list" aria-label="Collapse pinned messages">
            <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="xn-pinned__list" id="xn-pinned-list" role="list">
        @foreach ($pinned as $note)
            @php
                // A one-line reference, not a second copy of the message: whitespace is
                // collapsed so a multi-line note still occupies one row.
                $preview = Str::limit(trim(preg_replace('/\s+/u', ' ', $note->content)), 120);
                $keys = $note->tagKeys();
            @endphp
            <li class="xn-pinned__item">
                <button type="button" class="xn-pinned__jump" data-xn-jump="{{ $note->id }}" dir="auto">
                    {{ $preview }}
                </button>

                @if ($keys !== [])
                    <span class="xn-tags">
                        @foreach ($keys as $key)
                            <span class="xn-tag" title="{{ $tags[$key]['label'] }}">
                                <i class="{{ $tags[$key]['icon'] }}" aria-hidden="true"></i>
                                <span class="xn-sr">{{ $tags[$key]['label'] }}</span>
                            </span>
                        @endforeach
                    </span>
                @endif

                <button type="button" class="xn-act" data-xn-act="pin" data-id="{{ $note->id }}" title="Unpin" aria-label="Unpin this message">
                    <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                </button>
            </li>
        @endforeach
    </ul>
</section>
