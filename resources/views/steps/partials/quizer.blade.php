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
        <div id="task{{$task->id}}" class="gc-section step-task-card step-task-card--{{$taskType}} @if ($task->is_star) is-optional @endif @if ($hasUserSolution) is-submitted @endif overflow-hidden mb-3">
            @include('steps.partials.task_header')

            <div class="markdown step-task-card__body">
                {!! parsedown_math($task->text) !!}

                @if($blocked)
                    <p class="gc-help-note text-danger"><i class="fas fa-lock" aria-hidden="true"></i> Задача заблокирована. Новые сдачи запрещены.</p>
                @endif
                @if ($task->is_quiz && !$blocked)
                    @include('steps.partials.quiz-answer-form')
                @endif

                <div class="step-task-status-row {{$taskStatusClass}} @if (!($task->is_quiz && $hasUserSolution)) d-none @endif"
                     data-task-status-row>
                    @if ($task->is_quiz && $hasUserSolution)
                        <span class="badge rounded-pill {{ $taskScoreBadgeClass }} step-task-score" id="TSK_{{$task->id}}">{{$latestUserSolution->mark}} XP</span>
                        <span class="small text-muted" id="TSK_COM_{{$task->id}}">{{$latestUserSolution->comment}}</span>
                    @else
                        <span class="badge rounded-pill bg-body step-task-score" id="TSK_{{$task->id}}"></span>
                        <span class="small text-muted" id="TSK_COM_{{$task->id}}"></span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
@endif
