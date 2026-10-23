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
    'prefix' => null,
    'prefixTitle' => null,
])
{{-- One labelled field: label, control, hint, and an error slot page JS fills
     ([data-field-error], id "{form}-{name}-error"). `required` puts a static marker on the label;
     `required="toggle"` renders a hidden one ([data-field-req]) for pages whose rules change with
     the form's state (Projects). `prefix` sets a fixed lead-in in front of a text input. --}}
@php
    $id = $form . '-' . $name;
    $describedBy = trim(($hint ? $id . '-hint ' : '') . $id . '-error');
    $static = $required === true;
@endphp
<div {{ $attributes->class(['nd-field', 'nd-field--full' => $full]) }} data-field="{{ $name }}">
    <label class="nd-label" for="{{ $id }}">
        <span data-field-label>{{ $label }}</span>@if ($static)<span class="nd-req" aria-hidden="true">*</span>@elseif ($required === 'toggle')<span class="nd-req" data-field-req hidden aria-hidden="true">*</span>@endif
    </label>

    @if ($type === 'textarea')
        <textarea class="nd-input" id="{{ $id }}" name="{{ $name }}" rows="4" aria-describedby="{{ $describedBy }}"
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($static) required aria-required="true" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif></textarea>
    @elseif ($prefix)
        <div class="nd-affix">
            <span class="nd-affix__prefix" @if ($prefixTitle) title="{{ $prefixTitle }}" @endif>{{ $prefix }}</span>
            <input class="nd-input nd-input--mono" id="{{ $id }}" name="{{ $name }}" type="text" autocomplete="off" spellcheck="false" aria-describedby="{{ $describedBy }}"
                @if ($placeholder) placeholder="{{ $placeholder }}" @endif>
        </div>
    @else
        <input class="nd-input" id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" aria-describedby="{{ $describedBy }}"
            @if ($static) required aria-required="true" @endif
            @if ($dir) dir="{{ $dir }}" @endif
            @if ($accept) accept="{{ $accept }}" @endif
            @if (in_array($type, ['text', 'url'])) autocomplete="off" @endif
            @if ($type === 'number') inputmode="numeric" min="0" step="1" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif>
    @endif

    @if ($hint)
        <p class="nd-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <p class="nd-error" id="{{ $id }}-error" data-field-error hidden></p>
</div>
