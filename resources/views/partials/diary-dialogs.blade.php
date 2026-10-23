{{-- The entry page's dialogs. Each form carries _modal so a validation bounce reopens it. --}}

{{-- Seal ------------------------------------------------------------------------------- --}}
@unless ($entry->isLocked())
    <dialog class="nd-dialog nd-dialog--sm" id="dy-lock-dialog" aria-labelledby="dy-lock-title" data-dy-dialog="lock">
        <form method="POST" action="{{ url('/diary/' . $entry->id . '/lock') }}" novalidate data-dy-lock-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="lock:{{ $entry->id }}">

            <div class="nd-dialog__head">
                <div>
                    <h2 class="nd-dialog__title" id="dy-lock-title">Seal this memory</h2>
                    <p class="nd-dialog__sub">Its words become ciphertext. The title and the day stay readable.</p>
                </div>
                <button type="button" class="nd-icon-btn" data-dy-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="nd-dialog__body dy-dialog-stack">
                <p class="nd-note nd-note--warn">
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>The key is never stored, only a hash of it. Lose it and this memory is gone for good.</span>
                </p>

                <x-dy-algorithm form="lf" hint="You’ll need this algorithm and the key to open, unseal or delete it." />

                <div class="nd-field" data-dy-field="key" data-dy-lock-keyfield>
                    <label class="nd-label" for="lf-key">Key</label>
                    <x-nd-secret class="nd-input nd-input--mono" id="lf-key" name="key" minlength="8"
                           autocomplete="new-password" aria-describedby="lf-key-hint lf-key-error" data-dy-lock-key />
                    <p class="nd-hint" id="lf-key-hint">At least 8 characters.</p>
                    <p class="nd-error" id="lf-key-error" data-dy-error hidden></p>
                </div>

                <label class="nd-check" for="lf-generate">
                    <input type="checkbox" name="generate_key" id="lf-generate" value="1" data-dy-lock-generate>
                    <span>Make a strong key for me instead</span>
                </label>
            </div>

            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-dy-close>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--primary" data-dy-submit>
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    Seal it
                </button>
            </div>
        </form>
    </dialog>
@endunless

{{-- Unseal for good ------------------------------------------------------------------------ --}}
@if ($entry->isLocked())
    <dialog class="nd-dialog nd-dialog--sm" id="dy-unlock-dialog" aria-labelledby="dy-unlock-title" data-dy-dialog="unlock">
        <form method="POST" action="{{ url('/diary/' . $entry->id . '/unlock') }}" novalidate>
            @csrf
            @method('PATCH')
            <input type="hidden" name="_modal" value="unlock:{{ $entry->id }}">

            <div class="nd-dialog__head">
                <div>
                    <h2 class="nd-dialog__title" id="dy-unlock-title">Unseal for good</h2>
                    <p class="nd-dialog__sub">This writes it back as plain text and throws the key away. To read it just once, use <strong>Open it</strong> instead.</p>
                </div>
                <button type="button" class="nd-icon-btn" data-dy-close aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>

            <div class="nd-dialog__body dy-dialog-stack">
                <x-dy-algorithm form="uf" placeholder />
                <div class="nd-field" data-dy-field="key">
                    <label class="nd-label" for="uf-key">Key</label>
                    <x-nd-secret class="nd-input nd-input--mono" id="uf-key" name="key" required aria-required="true"
                           autocomplete="off" aria-describedby="uf-key-error" />
                    <p class="nd-error" id="uf-key-error" data-dy-error hidden></p>
                </div>
            </div>

            <div class="nd-dialog__foot">
                <button type="button" class="nd-btn nd-btn--ghost" data-dy-close>Cancel</button>
                <button type="submit" class="nd-btn nd-btn--primary" data-dy-submit>
                    <i class="fa-solid fa-lock-open" aria-hidden="true"></i>
                    Unseal
                </button>
            </div>
        </form>
    </dialog>
@endif

{{-- Delete --------------------------------------------------------------------------------- --}}
<dialog class="nd-dialog nd-dialog--sm" id="dy-delete-dialog" aria-labelledby="dy-delete-title" data-dy-dialog="delete">
    <form method="POST" action="{{ url('/diary/' . $entry->id) }}" novalidate>
        @csrf
        @method('DELETE')
        <input type="hidden" name="_modal" value="delete:{{ $entry->id }}">

        <div class="nd-dialog__head">
            <div>
                <h2 class="nd-dialog__title" id="dy-delete-title">Delete this memory?</h2>
                <p class="nd-dialog__sub">“<span dir="auto">{{ $entry->title }}</span>” will be gone for good. There is no undo.</p>
            </div>
        </div>

        @if ($entry->isLocked())
            <div class="nd-dialog__body dy-dialog-stack">
                <p class="nd-note nd-note--warn">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <span>It is sealed, so deleting it needs its key and algorithm too.</span>
                </p>
                <x-dy-algorithm form="df" placeholder />
                <div class="nd-field" data-dy-field="key">
                    <label class="nd-label" for="df-key">Key</label>
                    <x-nd-secret class="nd-input nd-input--mono" id="df-key" name="key" required aria-required="true"
                           autocomplete="off" aria-describedby="df-key-error" />
                    <p class="nd-error" id="df-key-error" data-dy-error hidden></p>
                </div>
            </div>
        @endif

        <div class="nd-dialog__foot">
            <button type="button" class="nd-btn nd-btn--ghost" data-dy-close autofocus>Keep it</button>
            <button type="submit" class="nd-btn nd-btn--danger" data-dy-submit>
                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                Delete
            </button>
        </div>
    </form>
</dialog>

{{-- The generated key: shown once, and only once --------------------------------------------- --}}
@php $generatedKey = session('generated_key'); @endphp
@if ($generatedKey)
    <dialog class="nd-dialog nd-dialog--sm nd-dialog--center" id="dy-key-dialog" aria-labelledby="dy-key-title" data-dy-key-dialog>
        <div class="nd-dialog__head">
            <h2 class="nd-dialog__title" id="dy-key-title"><i class="fa-solid fa-key" aria-hidden="true"></i> Your key</h2>
        </div>

        <div class="nd-dialog__body dy-dialog-stack">
            <p class="nd-note nd-note--warn">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <span>This is the only time it is ever shown. It isn’t stored anywhere, only a hash of it. Without it this memory can never be opened, unsealed or deleted.</span>
            </p>

            {{-- Big and monospace on purpose: meant to be read character by character. --}}
            <p class="dy-key" data-dy-key-value>{{ $generatedKey }}</p>

            <div class="dy-key__copy">
                <button type="button" class="nd-btn" data-dy-copy-key>
                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                    Copy the key
                </button>
                <span class="nd-hint" data-dy-copied role="status" aria-live="polite"></span>
            </div>

            <label class="nd-check dy-key__gate" for="dy-key-saved">
                <input type="checkbox" id="dy-key-saved" data-dy-key-gate>
                <span>I have saved this key somewhere I will still have it later.</span>
            </label>
        </div>

        <div class="nd-dialog__foot">
            {{-- No close button, no Esc, no backdrop click: the one place where friction is the
                 correct design. --}}
            <button type="button" class="nd-btn nd-btn--primary" data-dy-key-confirm disabled>Done, close this</button>
        </div>
    </dialog>
@endif
