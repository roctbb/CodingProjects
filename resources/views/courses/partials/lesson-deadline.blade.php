@if($lessonDeadlines->isNotEmpty())
    @php
        $firstDeadline = $lessonDeadlines->first();
        $sharedRule = $firstDeadline instanceof \App\Support\DeadlineRule && $firstDeadline->targetType === 'lesson'
            && $firstDeadline->targetId === $lesson->id && (!$isManager || !$firstDeadline->audience_id)
            && $lessonDeadlines->every(fn ($deadline) => $deadline instanceof \App\Support\DeadlineRule && $deadline->policyId === $firstDeadline->policyId);
        $deadlineAlert = match (true) {
            $isManager => \App\Support\LessonDeadlineStatus::forCalendar($lessonDeadlines),
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
        @if($sharedRule)
            <div class="course-lesson-date"><x-gc-deadline-chain :rule="$firstDeadline" :show-time="false" :note="$teacherDeadlineNote" compact /></div>
        @else
            @php
                $calendarDeadline = $lessonDeadlines->first(fn ($deadline) => ($deadline instanceof \App\Support\DeadlineRule
                    ? $deadline->cutoffAt() : $deadline->expiration->copy()->addDay())->isFuture()) ?? $lessonDeadlines->last();
            @endphp
            <x-gc-deadline-popover>
                <x-slot:summary><span class="visually-hidden">Сроки задач: </span><i class="fas fa-calendar-days" aria-hidden="true"></i><time datetime="{{ $calendarDeadline->expiration->toIso8601String() }}">{{ $calendarDeadline->expiration->format('d.m') }}</time></x-slot:summary>
                @if($teacherDeadlineNote)<p class="gc-help-text mb-2">{{ $teacherDeadlineNote }}</p>@endif
                <ul class="gc-lesson-deadlines list-unstyled mb-0">
                    @foreach($lessonDeadlines->unique(fn ($deadline) => ($deadline->audience_id ?? 0).':'.$deadline->task_id) as $deadline)
                        @php
                            $taskRule = $deadline instanceof \App\Support\DeadlineRule ? $deadline : \App\Support\DeadlineRule::fromLegacy($deadline);
                        @endphp
                        <li class="gc-lesson-deadline-group">
                            <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$deadline->task->step_id.'#task'.$deadline->task_id) }}">{{ $deadline->task->name }}</a>
                            @if($isManager && $deadline->audience_name)<span class="gc-help-text"> · {{ $deadline->audience_name }}</span>@endif
                            <ul>
                                @foreach($taskRule->series as $stage)
                                    @php($stageDate = $taskRule->deadlineAt($stage))
                                    <li>После <time datetime="{{ $stageDate->toIso8601String() }}">{{ $stageDate->format('d.m H:i') }}</time> — штраф {{ (float) round(100 - $stage['percent'], 2) }}%</li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </x-gc-deadline-popover>
        @endif
    </div>
@endif
