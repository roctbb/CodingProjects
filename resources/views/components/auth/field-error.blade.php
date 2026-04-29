@props([
    'field',
])

@if ($errors->has($field))
    <md-assist-chip {{ $attributes->class(['cp-field-error']) }} label="{{ $errors->first($field) }}" role="status"></md-assist-chip>
@endif
