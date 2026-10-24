@props(['what' => 'key', 'mask' => false])
{{-- A password or key field with a show/hide button (secret-fields.js). Every attribute given
     goes onto the input as written, autocomplete included; the button only switches the type.
     `mask` is for a field that must stay type="text" so the browser never offers to save it:
     there the button switches its CSS mask instead. --}}
<span class="nd-secret" data-secret @if ($mask) data-secret-mode="mask" @endif>
    <input type="{{ $mask ? 'text' : 'password' }}" {{ $attributes->class(['nd-secret__input']) }} data-secret-input>
    <button type="button" class="nd-secret__toggle" data-secret-toggle data-secret-what="{{ $what }}"
            aria-label="Show {{ $what }}" aria-pressed="false"
            @if ($attributes->has('id')) aria-controls="{{ $attributes->get('id') }}" @endif>
        <i class="fa-solid fa-eye" aria-hidden="true"></i>
    </button>
</span>
