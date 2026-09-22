@props(['form', 'placeholder' => false, 'hint' => null])
@php $id = $form . '-algorithm'; @endphp
{{-- Locking stores Hash::make($algorithm) alongside the key hash, so every later action has to
     name the algorithm again. Three options means it is a UX gate, not added security — the
     security rests on the key (CLAUDE.md §6). --}}
<div class="dy-field sm:col-span-2" data-dy-field="algorithm">
    <label class="dy-label" for="{{ $id }}">Algorithm<span class="dy-req" aria-hidden="true">*</span></label>
    <select class="dy-input dy-select" id="{{ $id }}" name="algorithm" required aria-required="true"
            aria-describedby="{{ trim(($hint ? $id . '-hint ' : '') . $id . '-error') }}">
        @if ($placeholder)
            <option value="">— the one you locked it with —</option>
        @endif
        <option value="aes">AES-256-CBC</option>
        <option value="chacha20">ChaCha20 (libsodium)</option>
        <option value="vigenere">Vigenère — for fun only, not secure</option>
    </select>
    @if ($hint)
        <p class="dy-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <p class="dy-error" id="{{ $id }}-error" data-dy-error hidden></p>
</div>
