@props(['course', 'lesson', 'expanded' => false, 'nextStep' => null, 'nextLabel' => 'Продолжить'])
<details {{ $attributes->class(['gc-lesson-outline']) }} @if($expanded) open @endif>
    <summary><span>Ступени урока <span class="gc-help-text">{{ $lesson->steps->count() }}</span></span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
    <ol class="list-unstyled mb-0">
        @foreach($lesson->steps as $lessonStep)
            <li><a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$lessonStep->id) }}" @class(['gc-lesson-outline__next' => $nextStep?->id === $lessonStep->id])>
                <span class="gc-lesson-outline__number" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $lessonStep->name }}</span>
                @if($nextStep?->id === $lessonStep->id)<span class="gc-lesson-outline__hint">{{ $nextLabel }}</span>@endif
            </a></li>
        @endforeach
    </ol>
</details>
