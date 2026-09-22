@php
    // One message in the stream. Rendered both by the page and, on its own, by every
    // turbo-stream response — which is why the id lives here: `replace` swaps the element
    // carrying it, so a card and its replacement have to agree on it.
    $keys = $note->tagKeys();
@endphp

<article class="xn-msg @if ($note->is_pinned) xn-msg--pinned @endif"
         id="xn-msg-{{ $note->id }}"
         data-xn-msg
         data-id="{{ $note->id }}"
         data-pinned="{{ $note->is_pinned ? '1' : '0' }}"
         data-tags="{{ implode(',', $keys) }}"
         {{-- The raw text, for copy and for prefilling the edit dialog without a round trip.
                  Reading it back out of the rendered HTML would give code-fence markup instead. --}}
         data-content="{{ $note->content }}">

    {{-- Plain click must never select: text gets selected inside these bubbles constantly,
         and click-to-select would fight that every single time. The checkbox is the explicit
         trigger, revealed on hover or once selection mode is on. --}}
    <label class="xn-msg__pick">
        <input type="checkbox" data-xn-pick aria-label="Select this message">
        <span class="xn-msg__box" aria-hidden="true"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
    </label>

    <div class="xn-msg__bubble">
        @if ($note->is_pinned)
            <span class="xn-msg__pin" title="Pinned">
                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
                <span class="xn-sr">Pinned</span>
            </span>
        @endif

        {{-- dir="auto" per message: Arabic prose and Latin code interleave here constantly,
             and the right base direction is per message, not per page. --}}
        <div class="xn-msg__text" dir="auto">{!! $formatter->render($note->content) !!}</div>

        <footer class="xn-msg__foot">
            @if ($keys !== [])
                <span class="xn-tags">
                    @foreach ($keys as $key)
                        {{-- Icons only — but an <i> is invisible to a screen reader and has no
                             tooltip, so each carries both a title and a visually hidden label. --}}
                        <span class="xn-tag" title="{{ $tags[$key]['label'] }}">
                            <i class="{{ $tags[$key]['icon'] }}" aria-hidden="true"></i>
                            <span class="xn-sr">{{ $tags[$key]['label'] }}</span>
                        </span>
                    @endforeach
                </span>
            @endif

            <time class="xn-msg__time" datetime="{{ $note->created_at->toIso8601String() }}">
                {{ $note->created_at->format('j M Y · H:i') }}
            </time>

            @if ($note->isEdited())
                {{-- updated_at is only ever written by an edit. Pinning and tagging go through
                     the query builder precisely so this marker stays truthful. --}}
                <span class="xn-msg__edited" title="Edited {{ $note->updated_at->format('j M Y · H:i') }}">edited</span>
            @endif
        </footer>

        {{-- Buttons, not forms: the stream is re-rendered constantly, and forms living inside
             a replaced card would be torn out mid-submit. The page keeps one shared form per
             action and JS points it at the right message. --}}
        <div class="xn-msg__actions" data-xn-actions>
            <button type="button" class="xn-act" data-xn-act="copy" data-id="{{ $note->id }}" title="Copy" aria-label="Copy this message">
                <i class="fa-solid fa-copy" aria-hidden="true"></i>
            </button>
            <button type="button" class="xn-act" data-xn-act="edit" data-id="{{ $note->id }}" title="Edit" aria-label="Edit this message">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
            </button>
            <button type="button" class="xn-act" data-xn-act="tags" data-id="{{ $note->id }}" title="Tags" aria-label="Change the tags on this message">
                <i class="fa-solid fa-tag" aria-hidden="true"></i>
            </button>
            <button type="button" class="xn-act @if ($note->is_pinned) xn-act--on @endif" data-xn-act="pin" data-id="{{ $note->id }}"
                    title="{{ $note->is_pinned ? 'Unpin' : 'Pin' }}"
                    aria-pressed="{{ $note->is_pinned ? 'true' : 'false' }}"
                    aria-label="{{ $note->is_pinned ? 'Unpin this message' : 'Pin this message' }}">
                <i class="fa-solid fa-thumbtack" aria-hidden="true"></i>
            </button>
            <button type="button" class="xn-act xn-act--danger" data-xn-act="delete" data-id="{{ $note->id }}" title="Delete" aria-label="Delete this message">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</article>
