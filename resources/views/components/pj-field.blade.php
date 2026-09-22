@props(['name', 'label', 'form', 'type' => 'text', 'hint' => null, 'full' => false, 'placeholder' => null])
@php
    $id = $form . '-' . $name;
    $describedBy = trim(($hint ? $id . '-hint ' : '') . $id . '-error');
    $root = rtrim(config('projects.wsl_root'), '/') . '/';
    $rootShort = preg_replace('#^/home/[^/]+/#', '~/', $root);
@endphp
<div class="pj-field {{ $full ? 'sm:col-span-2' : '' }}" data-pj-field="{{ $name }}">
    <label class="pj-label" for="{{ $id }}">
        <span data-pj-label>{{ $label }}</span><span class="pj-req" data-pj-req hidden aria-hidden="true">*</span>
    </label>

    @if ($type === 'textarea')
        <textarea class="pj-input" id="{{ $id }}" name="{{ $name }}" rows="4" aria-describedby="{{ $describedBy }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif></textarea>
    @elseif ($type === 'path')
        <div class="pj-affix">
            <span class="pj-affix__prefix pj-mono" title="{{ $root }}">{{ $rootShort }}</span>
            <input class="pj-input pj-mono" id="{{ $id }}" name="{{ $name }}" type="text" autocomplete="off" spellcheck="false" aria-describedby="{{ $describedBy }}" @if ($placeholder) placeholder="{{ $placeholder }}" @endif>
        </div>
    @else
        <input class="pj-input" id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" aria-describedby="{{ $describedBy }}"
            @if ($type === 'number') inputmode="numeric" min="0" step="1" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif>
    @endif

    @if ($hint)
        <p class="pj-hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif
    <p class="pj-error" id="{{ $id }}-error" data-pj-error hidden></p>
</div>
