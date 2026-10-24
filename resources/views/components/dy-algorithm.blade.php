@props(['form', 'placeholder' => false, 'hint' => null])
@php $id = $form . '-algorithm'; @endphp
{{-- Locking stores Hash::make($algorithm) alongside the key hash, so every later action has to
     name the algorithm again. Three options means it is a UX gate, not added security — the
     security rests on the key (CLAUDE.md §6). --}}
<div class="nd-field" data-dy-field="algorithm">
    <label class="nd-label" for="{{ $id }}">Algorithm</label>
    <select class="nd-input" id="{{ $id }}" name="algorithm" required aria-required="true"
            aria-describedby="{{ trim(($hint ? $id . '-hint ' : '') . $id . '-error') }}">
        @if ($placeholder)
            <option value="">The one it was sealed with</option>
        @endif
        <option value="aes">AES-256-CBC</option>
        <option value="chacha20">ChaCha20 (libsodium)</option>
        <option value="vigenere">Vigenère (for fun only, not secure)</option>
    </select>
    @if ($hint)
        <p class="nd-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <p class="nd-error" id="{{ $id }}-error" data-dy-error hidden></p>
</div>
