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
    'accept' => null,
])
@php
    $id = $form . '-' . $name;
    $describedBy = trim(($hint ? $id . '-hint ' : '') . $id . '-error');
@endphp
<div class="mu-field {{ $full ? 'sm:col-span-2' : '' }}" data-mu-field="{{ $name }}">
    <label class="mu-label" for="{{ $id }}">
        {{ $label }}@if ($required)<span class="mu-req" aria-hidden="true">*</span>@endif
    </label>

    <input class="mu-input"
           id="{{ $id }}"
           name="{{ $name }}"
           type="{{ $type }}"
           aria-describedby="{{ $describedBy }}"
           @if ($required) required aria-required="true" @endif
           @if ($dir) dir="{{ $dir }}" @endif
           @if ($accept) accept="{{ $accept }}" @endif
           @if ($type === 'text') autocomplete="off" @endif
           @if ($placeholder) placeholder="{{ $placeholder }}" @endif>

    @if ($hint)
        <p class="mu-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <p class="mu-error" id="{{ $id }}-error" data-mu-error hidden></p>
</div>
