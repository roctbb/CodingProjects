@php($worksheet = $worksheet ?? false)
@if($worksheet)
<header class="gc-task-title-row gc-task-title-row--{{ $isManager ? 'teacher' : 'student' }}">
@else
<div class="step-task-card__header gc-section-header">
    @if(!$isManager && empty($studentAttempt))<span class="gc-task-emblem" aria-hidden="true"><i class="{{ $taskTypeIcon }}"></i></span>@endif
@endif
    <div class="{{ $worksheet ? 'gc-task-title-group' : 'gc-task-heading min-width-0' }}">
        @if($worksheet && !empty($studentAttempt))
            <h1>{{ $task->name }}</h1>
        @elseif(empty($studentAttempt))
            <h2 @unless($worksheet) class="step-task-card__title mb-0" @endunless>{{ $task->name }}</h2>
        @endif
        @if($worksheet)
            @php($chatPanelId = ($isManager ? 'task-review-panel-' : 'task-chat-panel-').$task->id)
            @php($chatPanelLabel = $isManager ? 'панель' : 'чат')
            <button type="button" class="btn btn-sm btn-outline-secondary gc-icon-button gc-task-chat-toggle"
                    data-task-chat-toggle="{{ $chatPanelLabel }}" aria-controls="{{ $chatPanelId }}" aria-expanded="true"
                    aria-label="Свернуть {{ $chatPanelLabel }}" title="Свернуть {{ $chatPanelLabel }}" hidden>
                <i class="fas fa-angles-right" aria-hidden="true"></i>
            </button>
        @endif
        @if($worksheet)<div class="gc-task-title-facts">@endif
        @if($worksheet && !$isManager)
            @php($outcome = $taskOutcomes[$task->id])
            <div class="gc-task-outcome" data-task-outcome data-tone="{{ $outcome['tone'] }}" aria-live="polite">
                <span>{{ $outcome['label'] }}</span>
                <small title="Лучшая оценка за решение" @if($outcome['score'] === null) hidden @endif>{{ $outcome['score'] }}</small>
            </div>
            <span class="gc-task-title-meta">Максимум {{ trans_choice(':count балл|:count балла|:count баллов', $task->max_mark) }}</span>
        @endif
        @include('steps.partials.task-title-rewards')
        @if(!$worksheet || $isManager)
        <div class="gc-task-heading__type">
            <span class="step-task-type step-task-type--{{$taskType}}"><i class="{{$taskTypeIcon}}" aria-hidden="true"></i>{{$taskTypeLabel}}</span>
            <span class="gc-help-text">{{$task->max_mark}} XP</span>
        </div>
        @endif
        @if(!$worksheet && $isInsider && !$isManager && !$task->is_quiz)
            <div class="gc-task-metadata">
                <x-gc-task-state :task="$task" :viewer="$user" :course="$course" />
            </div>
        @endif
        @include('steps.partials.task-timing')
        @if($worksheet)</div>@endif
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
@if($worksheet)
    @if($studentSheet)
        <button type="button" class="btn btn-outline-secondary gc-task-chat-open" data-task-chat-open
                aria-haspopup="dialog" aria-controls="task-chat-dialog-{{ $task->id }}" aria-expanded="false" hidden>Чат задачи</button>
    @endif
    <div class="gc-task-chat-divider" data-task-chat-divider role="separator" aria-orientation="vertical" tabindex="0"
         aria-label="Ширина боковой панели" aria-controls="{{ $chatPanelId }}" title="Перетащите, чтобы изменить ширину" hidden></div>
</header>
@else
</div>
@endif
