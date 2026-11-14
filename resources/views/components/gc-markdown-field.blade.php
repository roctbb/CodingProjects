@props(['name', 'label', 'value' => '', 'hint' => null, 'compact' => false, 'required' => false, 'improve' => true, 'notebook' => false])
<x-gc-field :name="$name" :label="$label" :hint="$hint">
    @if($improve)
        <x-slot:actions><div class="dropdown"><button class="btn btn-outline-secondary btn-sm gc-text-tools" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Правка текста: {{ $label }}">Правка текста <i class="fas fa-chevron-down" aria-hidden="true"></i></button><div class="dropdown-menu dropdown-menu-end">
            @foreach(['fix_typos' => 'Исправить опечатки', 'improve_style' => 'Улучшить стиль', 'both' => 'Исправить и улучшить'] as $action => $title)
                <button type="button" class="dropdown-item" data-improve-text="{{ $action }}" data-field-id="{{ $name }}">{{ $title }}</button>
            @endforeach
        </div></div></x-slot:actions>
    @endif
    <textarea id="{{ $name }}" name="{{ $name }}" class="form-control" rows="{{ $compact ? 5 : 12 }}" data-markdown-editor data-editor-label="{{ $label }}" data-editor-compact="{{ $compact ? 'true' : 'false' }}" @if($notebook) data-notebook-source @endif @if($required) required @endif>{{ old($name, $value) }}</textarea>
</x-gc-field>
