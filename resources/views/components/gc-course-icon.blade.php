@props(['course' => null, 'name' => null])

@php
    $icon = $course ? \App\Support\CourseIcons::forCourse($course) : \App\Support\CourseIcons::resolve($name);
@endphp
<span {{ $attributes->class(['gc-course-icon']) }} aria-hidden="true">{!! \App\Support\CourseIcons::svg($icon) !!}</span>
