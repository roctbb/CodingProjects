@props(['course', 'lesson', 'nextStep' => null, 'nextLabel' => 'Продолжить'])
<ol {{ $attributes->class(['gc-lesson-outline', 'list-unstyled', 'mb-0']) }} aria-label="Ступени урока «{{ $lesson->name }}»">
        @foreach($lesson->steps as $lessonStep)
            <li><a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$lessonStep->id) }}" @class(['gc-lesson-outline__next' => $nextStep?->id === $lessonStep->id])>
                <span class="gc-lesson-outline__number" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $lessonStep->name }}</span>
                @if($nextStep?->id === $lessonStep->id)<span class="gc-lesson-outline__hint">{{ $nextLabel }}</span>@endif
            </a></li>
        @endforeach
</ol>
