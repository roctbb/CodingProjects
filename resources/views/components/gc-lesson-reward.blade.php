@props(['lesson', 'course', 'sticker' => false, 'status' => 'preview'])
@php
    $label = $status === 'earned' ? 'Полученная наклейка за урок «'.$lesson->name.'»'
        : ($status === 'catalog' ? 'Наклейка за урок «'.$lesson->name.'»' : 'Наклейка за урок «'.$lesson->name.'» ещё не получена');
@endphp
<span {{ $attributes->class(['gc-lesson-reward', 'is-preview' => $sticker && $status === 'preview']) }} data-lesson-reward="{{ $sticker ? $status : 'icon' }}" @if($sticker) title="{{ $label }}" @endif>
    @if($sticker)
        <img src="{{ url($lesson->sticker) }}" alt="{{ $label }}" width="96" height="96" loading="lazy">
    @else
        <x-gc-course-icon :course="$course" />
    @endif
</span>
