@if($lessonDeadlines->isNotEmpty() || $lessonDeadline)
    @php
        $deadlineAlert = match (true) {
            $isManager => \App\Support\LessonDeadlineStatus::forCalendar($lessonDeadlines->isNotEmpty() ? $lessonDeadlines : [$lessonDeadline]),
            $isLearner && $isAvailable => \App\Support\LessonDeadlineStatus::forLesson($lesson, $course, $user, $lessonView['rows'] ?? null),
            default => ['status' => null, 'first' => null],
        };
        $deadlineAlertLabel = match ($deadlineAlert['status']) {
            'overdue' => 'Первый срок сдачи прошёл',
            'today' => 'Первый срок сдачи сегодня',
            default => 'Сроки сдачи',
        };
        $teacherDeadlineNote = $isManager && $lessonTaskCount > 0 && $lessonCard['total'] ? 'Не завершили урок: '.$lessonCard['unfinished'].' из '.$lessonCard['total'] : null;
    @endphp
    <div class="course-lesson-deadline" role="group" aria-label="{{ $deadlineAlertLabel }}" title="{{ $deadlineAlertLabel }}"
         data-lesson-deadline-status="{{ $deadlineAlert['status'] }}"
         @if($deadlineAlert['first']) data-lesson-deadline-first="{{ json_encode($deadlineAlert['first']) }}" data-deadline-server-now="{{ $deadlineAlert['now'] }}" @endif>
@if($lessonDeadlines->isNotEmpty() && !($lessonDeadline instanceof \App\Support\DeadlineRule))
    <x-gc-deadline-popover>
        <x-slot:summary><span class="visually-hidden">Сроки сдачи: </span><i class="fas fa-calendar-days" aria-hidden="true"></i><time datetime="{{ $lessonCalendarDeadline->expiration->toIso8601String() }}">{{ $lessonCalendarDeadline->expiration->format('d.m') }}</time></x-slot:summary>
        @if($teacherDeadlineNote)<p class="gc-help-text mb-2">{{ $teacherDeadlineNote }}</p>@endif
        <ul class="gc-lesson-deadlines list-unstyled mb-0">
            @foreach($lessonDeadlines->unique('task_id') as $deadline)
                @php
                    $taskRule = $deadline instanceof \App\Support\DeadlineRule ? $deadline : \App\Support\DeadlineRule::fromLegacy($deadline);
                @endphp
                <li class="gc-lesson-deadline-group">
                    <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$deadline->task->step_id.'#task'.$deadline->task_id) }}">{{ $deadline->task->name }}</a>
                    <ul>
                        @foreach($taskRule->series as $stage)
                            @php
                                $stageDate = $taskRule->deadlineAt($stage);
                            @endphp
                            <li>После <time datetime="{{ $stageDate->toIso8601String() }}">{{ $stageDate->format('d.m H:i') }}</time> — штраф {{ (float) round(100 - $stage['percent'], 2) }}%</li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </x-gc-deadline-popover>
@endif
@if ($lessonDeadline && ($lessonDeadline instanceof \App\Support\DeadlineRule || !$isLearner || $lessonDeadlines->isEmpty()))
    @if($lessonDeadline instanceof \App\Support\DeadlineRule && !empty($lessonDeadline->series))
        <div class="course-lesson-date"><x-gc-deadline-chain :rule="$lessonDeadline" :show-time="false" :note="$teacherDeadlineNote" compact /></div>
    @else
        <div class="course-lesson-date"><x-gc-deadline-chain :rule="\App\Support\DeadlineRule::fromLegacy($lessonDeadline)" :show-time="false" :note="$teacherDeadlineNote" compact /></div>
    @endif
@endif
    </div>
@endif
