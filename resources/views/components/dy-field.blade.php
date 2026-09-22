@props([
    'name',
    'label',
    'form',
    'type' => 'text',
    'hint' => null,
    'full' => false,
    'placeholder' => null,
    'dir' => null,
    'required' => false,
    'rows' => 8,
    'accept' => null,
])
@php
    $id = $form . '-' . $name;
    $describedBy = trim(($hint ? $id . '-hint ' : '') . $id . '-error');
@endphp
<div class="dy-field {{ $full ? 'sm:col-span-2' : '' }}" data-dy-field="{{ $name }}">
    <label class="dy-label" for="{{ $id }}">
        {{ $label }}@if ($required)<span class="dy-req" aria-hidden="true">*</span>@endif
    </label>

    @if ($type === 'textarea')
        <textarea class="dy-input dy-input--area"
                  id="{{ $id }}"
                  name="{{ $name }}"
                  rows="{{ $rows }}"
                  aria-describedby="{{ $describedBy }}"
                  @if ($required) required aria-required="true" @endif
                  @if ($dir) dir="{{ $dir }}" @endif
                  @if ($placeholder) placeholder="{{ $placeholder }}" @endif></textarea>
    @else
        <input class="dy-input"
               id="{{ $id }}"
               name="{{ $name }}"
               type="{{ $type }}"
               aria-describedby="{{ $describedBy }}"
               @if ($required) required aria-required="true" @endif
               @if ($dir) dir="{{ $dir }}" @endif
               @if ($accept) accept="{{ $accept }}" @endif
               @if ($type === 'text') autocomplete="off" @endif
               @if ($placeholder) placeholder="{{ $placeholder }}" @endif>
    @endif

    @if ($hint)
        <p class="dy-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <p class="dy-error" id="{{ $id }}-error" data-dy-error hidden></p>
</div>
