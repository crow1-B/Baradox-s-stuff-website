<section class="xn-pinned" id="xn-pinned" data-xn-pinned aria-label="Pinned messages" @if ($pinned->isEmpty()) hidden @endif>
    <div class="xn-pinned__head">
        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
        <span>Pinned</span>
        <span class="xn-pinned__count">{{ $pinned->count() }}</span>
        <button type="button" class="nd-icon-btn nd-icon-btn--sm xn-pinned__toggle" data-xn-pinned-toggle aria-expanded="true" aria-controls="xn-pinned-list" aria-label="Collapse pinned messages">
            <i class="fa-solid fa-chevron-up" aria-hidden="true"></i>
        </button>
    </div>

    <ul class="xn-pinned__list" id="xn-pinned-list" role="list">
        @foreach ($pinned as $note)
            @php
                $preview = Str::limit(trim(preg_replace('/\s+/u', ' ', $note->content)), 120);
                $keys = $note->tagKeys();
            @endphp
            <li class="xn-pinned__item">
                <button type="button" class="xn-pinned__jump nd-written" data-xn-jump="{{ $note->id }}" dir="auto">
                    {{ $preview }}
                </button>

                @if ($keys !== [])
                    <span class="xn-tags">
                        @foreach ($keys as $key)
                            <span class="xn-tag" title="{{ $tags[$key]['label'] }}">
                                <i class="{{ $tags[$key]['icon'] }}" aria-hidden="true"></i>
                                <span class="nd-sr-only">{{ $tags[$key]['label'] }}</span>
                            </span>
                        @endforeach
                    </span>
                @endif

                @if ($canWrite)
                    <button type="button" class="nd-icon-btn nd-icon-btn--sm" data-xn-act="pin" data-id="{{ $note->id }}" title="Unpin" aria-label="Unpin this message">
                        <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                    </button>
                @endif
            </li>
        @endforeach
    </ul>
</section>
