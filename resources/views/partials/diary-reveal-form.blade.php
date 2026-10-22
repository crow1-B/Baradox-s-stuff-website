<form method="POST" action="{{ route('diary.reveal', $entry->id) }}" class="dy-reveal" novalidate>
    @csrf

    <p class="dy-muted text-sm">
        Give the key and the algorithm this memory was locked with. It is decrypted for this one
        view, in memory, and never written back.
    </p>

    @unless ($pristine)
        @error('key')
            {{-- lock, unlock and delete all report through this same 'key' bag, and all three set
                 a reopen marker. Only a failed reveal leaves it empty, so this shows nothing but
                 our own error. --}}
            @if (($reopen ?? null) === null)
                <p class="dy-note dy-note--error" role="alert">
                    <x-dy-icon name="alert" />
                    {{-- One combined message on purpose: naming which half was wrong would narrow
                         the three-value algorithm space for free (CLAUDE.md §6). --}}
                    <span>{{ $message }}</span>
                </p>
            @endif
        @enderror
    @endunless

    <div class="mt-4 grid grid-cols-1 gap-4">
        <x-dy-algorithm form="rf{{ $entry->id }}" placeholder />

        <div class="dy-field" data-dy-field="key">
            <label class="dy-label" for="rf{{ $entry->id }}-key">Key<span class="dy-req" aria-hidden="true">*</span></label>
            <input class="dy-input dy-mono" type="password" id="rf{{ $entry->id }}-key" name="key" required aria-required="true"
                   autocomplete="off" aria-describedby="rf{{ $entry->id }}-key-error">
            <p class="dy-error" id="rf{{ $entry->id }}-key-error" data-dy-error hidden></p>
        </div>
    </div>

    <div class="dy-reveal__foot">
        <button type="submit" class="dy-btn dy-btn--primary">
            <x-dy-icon name="eye" />
            Open it
        </button>
    </div>
</form>
