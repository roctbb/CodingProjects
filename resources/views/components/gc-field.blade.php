@props(['name', 'label', 'id' => null, 'hint' => null])
@php
    $fieldId = $id ?? str_replace(['[', ']'], ['-', ''], $name);
    $fieldErrors = collect($errors->get($name))->flatten();
@endphp
<div {{ $attributes->class(['gc-field']) }} data-field="{{ $fieldId }}">
    <div class="gc-field__heading"><label id="{{ $fieldId }}-label" for="{{ $fieldId }}" class="form-label">{{ $label }}</label>@isset($actions){{ $actions }}@endisset</div>
    {{ $slot }}
    @if($hint)<p class="gc-help-text mb-0" id="{{ $fieldId }}-help">{{ $hint }}</p>@endif
    <p class="gc-field__error mb-0" id="{{ $fieldId }}-error" data-field-error @if($fieldErrors->isEmpty()) hidden @endif>{{ $fieldErrors->first() }}</p>
</div>
