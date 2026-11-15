@php($hasDeadlineChain = $deadline instanceof \App\Support\DeadlineRule && !empty($deadline->series))
<div class="step-task-card__header gc-section-header">
    @if(!$isManager)<span class="gc-task-emblem" aria-hidden="true"><i class="{{ $taskTypeIcon }}"></i></span>@endif
    <div class="gc-task-heading min-width-0">
        <h2 class="step-task-card__title mb-0">{{$task->name}}</h2>
        <div class="gc-task-heading__type">
            <span class="step-task-type step-task-type--{{$taskType}}"><i class="{{$taskTypeIcon}}" aria-hidden="true"></i>{{$taskTypeLabel}}</span>
            <span class="gc-help-text">{{$task->max_mark}} XP</span>
        </div>
        <div class="gc-task-metadata">
            @if($isInsider && !$isManager && !$task->is_quiz)
                <x-gc-task-state :task="$task" :viewer="$user" :course="$course" />
            @endif
            @if($deadline && !$hasDeadlineChain)
                <span class="gc-task-metadata__deadline"><span class="gc-help-text">{{ $isManager ? 'Срок сдачи:' : 'Срок:' }}</span> <x-gc-deadline-label :expiration="$deadline->expiration" :rule="$deadline" :personal="!$isManager" :settled="!$isManager && $task->isDone($user->id)" /></span>
            @endif
            @if($earnedAchievementId)
                <a class="step-task-achievement-badge" href="{{ url('/insider/profile/'.$user->id.'#achievement-'.$earnedAchievementId) }}"><i class="fas fa-trophy" aria-hidden="true"></i> Достижение получено</a>
            @endif
            @if($task->price > 0)
                <span class="gc-task-reward"><i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i> {{$task->price}} GC</span>
            @endif
        </div>
        @if($hasDeadlineChain)<span class="gc-task-deadline-chain"><i class="fas fa-calendar-days" aria-hidden="true"></i> <x-gc-deadline-chain :rule="$deadline" /></span>@endif
        @if($isInsider && !$isManager)
            @php($classBonus = app(\App\Services\ClassroomBonus::class)->at($task, $course->id, $user->id, now()))
            @if(!empty($classBonus['amount']))<p class="gc-help-note mt-2 mb-0"><i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i> Решите полностью на занятии: +{{ $classBonus['amount'] }} GC. Сдайте до {{ \Carbon\Carbon::parse($classBonus['ends_at'])->setTimezone(config('app.timezone'))->format('H:i') }}.</p>@endif
        @endif
    </div>
    @if ($isManager)
        @include('steps.partials.deadline_modal')
        <div class="dropdown step-task-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с задачей {{ $task->name }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
            <div class="dropdown-menu dropdown-menu-end">
                <a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"><i class="fas fa-pen" aria-hidden="true"></i>Редактировать задачу</a>
                <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#deadline-modal-{{$task->id}}"><i class="fas fa-calendar-days" aria-hidden="true"></i>Дедлайн</button>
                @if(!$quizer)<button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#task-ai-summary-modal-{{$task->id}}"><i class="fas fa-newspaper" aria-hidden="true"></i>Пересказ решений</button>@endif
                <a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}" data-confirm="Добавить пустое фантомное решение для всех студентов?"><i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>Фантомные решения</a>
                @if ($task->is_code && empty($nativeTaskChecker))
                    <a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/recheck-all')}}" data-confirm="Вы уверены? Это обнулит все баллы и отправит последние решения на перепроверку."><i class="fas fa-rotate" aria-hidden="true"></i>Перепроверить решения</a>
                @elseif(!empty($nativeTaskChecker))
                    <button type="button" class="dropdown-item" data-execution-recheck-latest="#execution-workspace-{{ $task->id }}"><i class="fas fa-rotate" aria-hidden="true"></i>Перепроверить последние работы группы</button>
                @endif
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"><i class="fas fa-arrow-left" aria-hidden="true"></i>Сдвинуть влево</a>
                <a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"><i class="fas fa-arrow-right" aria-hidden="true"></i>Сдвинуть вправо</a>
                @if ($step->previousStep())<a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"><i class="fas fa-arrow-up" aria-hidden="true"></i>Перенести выше</a>@endif
                @if ($step->nextStep())<a class="dropdown-item" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"><i class="fas fa-arrow-down" aria-hidden="true"></i>Перенести ниже</a>@endif
                <div class="dropdown-divider"></div>
                <a class="dropdown-item text-danger" href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}" data-confirm="Вы уверены?"><i class="fas fa-trash" aria-hidden="true"></i>Удалить задачу</a>
            </div>
        </div>
    @endif
</div>
