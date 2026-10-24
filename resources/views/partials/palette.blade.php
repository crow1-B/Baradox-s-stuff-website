@php
    $canWrite = !Auth::user()->isPreview();
    $shellData = [
        'pages' => \App\Support\Shell\Navigation::pages(),
        'itemsUrl' => route('palette'),
        'writeUrl' => $canWrite ? route('diary', ['write' => 1]) : null,
    ];
    $goKeys = collect($shellData['pages'])->map(fn ($page) => [$page['label'], $page['key']]);
@endphp

{{-- Ctrl+K. Pages are rendered with the page; memories, songs and the rest are fetched when
     it opens, from routes that never list anything local-only. --}}
<dialog class="nd-palette" id="nd-palette" aria-label="Jump to" data-palette>
    <div class="nd-palette__search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input class="nd-palette__input" type="text" role="combobox" aria-expanded="true" aria-controls="nd-palette-list"
               aria-autocomplete="list" aria-label="Jump to a page, memory or song" placeholder="Jump to a page, memory or song"
               autocomplete="off" spellcheck="false" dir="auto" data-palette-input>
        <kbd class="nd-kbd">Esc</kbd>
    </div>
    <ul class="nd-palette__list" id="nd-palette-list" role="listbox" aria-label="Results" data-palette-list></ul>
    <div class="nd-palette__foot" aria-hidden="true">
        <span><kbd class="nd-kbd">↑</kbd><kbd class="nd-kbd">↓</kbd> move</span>
        <span><kbd class="nd-kbd">Enter</kbd> open</span>
        <span><kbd class="nd-kbd">?</kbd> all shortcuts</span>
    </div>
</dialog>

<dialog class="nd-dialog nd-dialog--wide nd-dialog--center" id="nd-shortcuts" aria-labelledby="nd-shortcuts-title" data-shortcuts>
    <div class="nd-dialog__head">
        <h2 class="nd-dialog__title" id="nd-shortcuts-title">Keyboard shortcuts</h2>
        <button type="button" class="nd-icon-btn" data-dialog-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>
    <div class="nd-dialog__body">
        <div class="nd-shortcuts__groups">
            <section>
                <h3 class="nd-shortcuts__title">Anywhere</h3>
                <dl class="nd-shortcuts__list">
                    <div class="nd-shortcuts__row"><dt>Jump to anything</dt><dd><kbd class="nd-kbd" data-mod-key>Ctrl</kbd><kbd class="nd-kbd">K</kbd></dd></div>
                    @if ($canWrite)
                        <div class="nd-shortcuts__row"><dt>Close the day (write today’s entry)</dt><dd><kbd class="nd-kbd">W</kbd></dd></div>
                    @endif
                    <div class="nd-shortcuts__row"><dt>Play or pause the music</dt><dd><kbd class="nd-kbd">P</kbd></dd></div>
                    <div class="nd-shortcuts__row"><dt>Change the light</dt><dd><kbd class="nd-kbd">T</kbd></dd></div>
                    <div class="nd-shortcuts__row"><dt>Search this page</dt><dd><kbd class="nd-kbd">/</kbd></dd></div>
                    <div class="nd-shortcuts__row"><dt>This list</dt><dd><kbd class="nd-kbd">?</kbd></dd></div>
                </dl>
            </section>
            <section>
                <h3 class="nd-shortcuts__title">Go to a page</h3>
                <dl class="nd-shortcuts__list">
                    @foreach ($goKeys as [$label, $key])
                        <div class="nd-shortcuts__row"><dt>{{ $label }}</dt><dd><kbd class="nd-kbd">G</kbd><kbd class="nd-kbd">{{ strtoupper($key) }}</kbd></dd></div>
                    @endforeach
                </dl>
            </section>
            <section>
                <h3 class="nd-shortcuts__title">Reading the diary</h3>
                <dl class="nd-shortcuts__list">
                    <div class="nd-shortcuts__row"><dt>Older entry</dt><dd><kbd class="nd-kbd">←</kbd></dd></div>
                    <div class="nd-shortcuts__row"><dt>Newer entry</dt><dd><kbd class="nd-kbd">→</kbd></dd></div>
                    <div class="nd-shortcuts__row"><dt>Close the entry</dt><dd><kbd class="nd-kbd">Esc</kbd></dd></div>
                </dl>
            </section>
        </div>
    </div>
</dialog>

<script type="application/json" id="nd-shell-data">@json($shellData)</script>
