@props(['course' => null])

@php
    $selected = old('icon_key', $course?->icon_key ?? 'auto');
    $automaticIcon = \App\Support\CourseIcons::resolve(null, (string) old('name', $course?->name ?? ''));
@endphp
<fieldset class="gc-icon-picker">
    <legend class="form-label">Иконка курса</legend>
    <details @if($errors->has('icon_key')) open @endif data-icon-picker>
        <summary><span class="gc-icon-picker__preview" data-icon-preview><x-gc-course-icon :name="$selected === 'auto' ? $automaticIcon : $selected" /></span><span data-icon-label>{{ (['auto' => 'Автоматически'] + \App\Support\CourseIcons::options())[$selected] ?? 'Автоматически' }}</span><span class="btn btn-outline-secondary btn-sm ms-auto">Изменить</span></summary>
    <div class="gc-icon-picker__options">
        @foreach(['auto' => 'Автоматически'] + \App\Support\CourseIcons::options() as $key => $label)
            <label class="gc-icon-option">
                <input class="visually-hidden" type="radio" name="icon_key" value="{{ $key }}" @checked($selected === $key)>
                <span class="gc-icon-option__content">
                    <x-gc-course-icon :name="$key === 'auto' ? $automaticIcon : $key" />
                    <span>{{ $label }}</span>
                </span>
            </label>
        @endforeach
    </div>
    </details>
    <p class="form-text mb-0">Иконка показывается на карточке курса. Автовыбор подбирает её по названию; можно выбрать другую вручную.</p>
    @error('icon_key')<p class="text-danger small mt-1 mb-0">{{ $message }}</p>@enderror
</fieldset>
