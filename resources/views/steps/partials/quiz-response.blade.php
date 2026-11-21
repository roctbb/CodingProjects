@php
    $hasAnswer = $latestUserSolution?->submitted && filled($latestUserSolution->text);
    $remaining = $task->quizAttemptsRemaining($user->id, $course->id);
    $hasQuizErrors = $errors->getBag('quiz_'.$task->id)->any();
@endphp
<div class="gc-quiz-response" data-quiz-response>
    <div class="gc-quiz-result @unless($hasAnswer) d-none @endunless" data-task-status-row role="status">
        <strong id="TSK_COM_{{ $task->id }}">{{ $hasAnswer ? $latestUserSolution->comment : '' }}</strong>
        <span id="TSK_{{ $task->id }}" class="badge rounded-pill {{ $taskScoreBadgeClass }}">{{ $hasAnswer && $latestUserSolution->mark !== null ? $latestUserSolution->mark.' XP' : '' }}</span>
        <a class="gc-quiet-link" data-quiz-history @if($hasAnswer) href="{{ app(\App\Services\TaskLocation::class)->task($course->id, $task, $user->id, 'solution-'.$latestUserSolution->id) }}" @else hidden @endif>История ответов</a>
        <div class="gc-quiz-given">Ваш ответ <strong data-quiz-given>{{ $hasAnswer ? $latestUserSolution->text : '' }}</strong></div>
    </div>
    @if($blocked)
        <p class="gc-help-note">Задача заблокирована. Новые ответы запрещены.</p>
    @else
        <details data-quiz-entry @if(!$hasAnswer || $hasQuizErrors) open @endif @if($remaining === 0) hidden @endif>
            <summary @unless($hasAnswer) hidden @endunless>Ответить ещё раз</summary>
            @include('steps.partials.quiz-answer-form', ['attemptsRemaining' => $remaining])
        </details>
        <p class="gc-help-text mb-0" data-quiz-exhausted @unless($remaining === 0) hidden @endunless>Попытки исчерпаны.</p>
    @endif
    @if($isManager)
        <details class="gc-quiz-reviews" @if(($taskReviewContext['task_id'] ?? null) === $task->id) open @endif>
            <summary>Ответы учеников</summary>
            <label class="gc-field my-2">Ученик
                <select class="form-select" data-quiz-student-for="execution-workspace-{{ $task->id }}">
                    <option value="">Выбрать ученика</option>
                    @foreach($course->students as $participant)
                        <option value="{{ $participant->id }}">{{ $participant->name }}</option>
                    @endforeach
                </select>
            </label>
            @include('steps.partials.native-workspace', ['nativeTeacher'=>true, 'nativeHistoryOnly'=>true])
        </details>
    @endif
</div>
