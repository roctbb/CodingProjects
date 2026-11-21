@if (\Request::is('insider/*') && $quizer)
    @foreach ($tasks as $key => $task)
        @php
            $isInsider = true;
            $isManager = $course->teachers->contains($user) || $user->role === 'admin';
            $deadline = $task->getDeadline($course->id);
            $blocked = $task->isBlocked($user->id, $course->id);
            $latestUserSolution = Auth::check() ? $task->latestSolutionForUser(Auth::id()) : null;
            $hasUserSolution = $latestUserSolution !== null;
            $taskType = $task->is_code ? 'code' : ($task->is_quiz ? 'quiz' : 'text');
            $taskTypeLabel = ['code' => 'Код', 'quiz' => 'Квиз', 'text' => 'Ответ'][$taskType];
            $taskTypeIcon = ['code' => 'fas fa-code', 'quiz' => 'fas fa-question-circle', 'text' => 'fas fa-pen'][$taskType];
            $earnedAchievementId = ($earnedTaskAchievements ?? collect())->get($task->id);
            $taskStatusClass = '';
            if ($hasUserSolution) {
                $taskStatusClass = is_null($latestUserSolution->mark)
                    ? 'is-review'
                    : ((int) $latestUserSolution->mark >= (int) $task->max_mark ? 'is-complete' : 'is-submitted');
            }
            $taskScoreBadgeClass = $hasUserSolution ? $latestUserSolution->scoreBadgeClass('bg-body') : 'bg-body';
        @endphp
        <section id="task{{$task->id}}" class="gc-section step-task-card step-task-card--quiz gc-quiz-card mb-3">
            @include('steps.partials.task_header')
            <div class="step-task-card__body">
                <div class="markdown">{!! parsedown_math($task->text) !!}</div>
                @include('steps.partials.quiz-response')
            </div>
        </section>
    @endforeach
@endif
