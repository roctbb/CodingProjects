@php
    $isInsider = \Request::is('insider/*');
    $stepBaseUrl = $isInsider && $course ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/';
    $lessonSteps = $step->lesson->steps;
@endphp
<details class="gc-workbook-steps" data-workbook-navigation open>
    <summary class="gc-workbook-steps__toggle"><span>Ступени урока</span><i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
    <div class="gc-workbook-steps__content">
        <a class="gc-workbook-brand" href="{{ $isInsider && $course ? url('/insider/courses/'.$course->id.'?chapter='.$step->lesson->chapter_id) : url('/') }}"><x-brand-logo width="24" height="24" alt="" /><span>{{ config('app.name', 'Laravel') }}</span></a>
        <div class="gc-workbook-steps__heading"><h2>Ступени урока</h2></div>
        <nav class="gc-workbook-steps__list" aria-label="Ступени урока">
            @foreach($lessonSteps as $lessonStep)
                @php
                    $hasVisibleTasks = $lessonStep->tasks->contains(fn ($task) => $isInsider
                        ? $task->isVisible($user, $course)
                        : !$task->is_hidden);
                @endphp
                <a class="gc-workbook-steps__link" href="{{ url($stepBaseUrl.$lessonStep->id) }}" @if($lessonStep->id == $step->id) aria-current="page" @endif>
                    <span class="gc-workbook-steps__number" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $lessonStep->name }}</span>
                    @if($hasVisibleTasks)
                        <span class="gc-workbook-steps__tasks" title="Есть задания"><i class="fas fa-trophy" aria-hidden="true"></i><span class="visually-hidden">Есть задания</span></span>
                    @endif
                </a>
            @endforeach
        </nav>
        @include('steps.partials.add_step')
    </div>
</details>
