@props(['name', 'label', 'checked' => false, 'value' => 'on', 'hint' => null, 'id' => null])
@php
    $toggleId = $id ?? $name;
@endphp
<input type="hidden" name="{{ $name }}" value="0">
<label class="gc-toggle" for="{{ $toggleId }}">
    <input {{ $attributes->class(['form-check-input']) }} type="checkbox" id="{{ $toggleId }}" name="{{ $name }}" value="{{ $value }}" @checked((string) old($name, $checked ? $value : '0') === (string) $value)>
    <span><span class="gc-toggle__label">{{ $label }}</span>@if($hint)<span class="gc-help-text d-block">{{ $hint }}</span>@endif</span>
</label>
