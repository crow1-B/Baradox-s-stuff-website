{{-- A sealed entry at rest: the seal, and the two things that open it. Rendered inside the
     entry's <turbo-frame>, and kept pristine in a <template> beside it. --}}
<div class="dy-sealed" data-dy-sealed>
    @include('partials.diary-wax', ['class' => 'dy-sealed__wax'])
    <p class="dy-sealed__line">Sealed. Its key and algorithm open it, for this one reading.</p>

    <form method="POST" action="{{ route('diary.reveal', $entry->id) }}" class="dy-reveal" novalidate>
        @csrf

        @unless ($pristine)
            @error('key')
                {{-- lock, unlock and delete report through this same 'key' bag, and all three
                     set a reopen marker. Only a failed reveal leaves it empty. One combined
                     message: naming which half was wrong would narrow the algorithm for free. --}}
                @if (($reopen ?? null) === null)
                    <p class="nd-note nd-note--error" role="alert"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>{{ $message }}</span></p>
                @endif
            @enderror
        @endunless

        <div class="dy-reveal__fields">
            <x-dy-algorithm form="rf{{ $entry->id }}" placeholder />
            <div class="nd-field" data-dy-field="key">
                <label class="nd-label" for="rf{{ $entry->id }}-key">Key</label>
                <x-nd-secret class="nd-input nd-input--mono" id="rf{{ $entry->id }}-key" name="key" required aria-required="true"
                       autocomplete="off" aria-describedby="rf{{ $entry->id }}-key-error" />
                <p class="nd-error" id="rf{{ $entry->id }}-key-error" data-dy-error hidden></p>
            </div>
        </div>

        <button type="submit" class="nd-btn nd-btn--primary">
            <i class="fa-solid fa-envelope-open-text" aria-hidden="true"></i>
            Open it
        </button>
    </form>
</div>
