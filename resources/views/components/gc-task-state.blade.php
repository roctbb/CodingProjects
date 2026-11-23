@props(['task', 'viewer', 'course', 'compact' => false, 'outcome' => null])

@php
    // Tasks on the workbook are eager-loaded within the current course.
    $blocked = $task->isBlocked($viewer->id, $course->id);
    $pending = !$blocked && $task->isOnCheck($viewer->id);
    $complete = !$blocked && !$pending && $task->isFullDone($viewer->id);
    $submitted = ($outcome && $outcome['tone'] !== 'empty') || $task->isSubmitted($viewer->id);
    $exhausted = $task->is_quiz && $submitted && $task->quizAttemptsRemaining($viewer->id, $course->id) === 0;
    $rule = $compact && !$submitted ? $task->getDeadline($course->id) : null;
    $expiration = $rule?->expiration;
    $overdue = $expiration && ($rule instanceof \App\Support\DeadlineRule ? $rule->cutoffAt() : $expiration->copy()->addDay())->lte(now());
    $soon = $expiration && $expiration->lt(now()->addDays(3));
    [$label, $icon, $tone] = match (true) {
        $blocked => ['Заблокировано', 'lock', 'danger'],
        $outcome && $outcome['tone'] !== 'empty' => [$outcome['label'], $outcome['icon'], $outcome['tab_tone']],
        $pending => ['На проверке', 'hourglass-half', 'pending'],
        $complete => ['Выполнено', 'circle-check', 'complete'],
        $exhausted => ['Попытки исчерпаны', 'circle-xmark', 'neutral'],
        $submitted => ['Можно улучшить', 'circle-half-stroke', 'neutral'],
        $overdue => ['Срок истёк', 'clock', 'danger'],
        $soon => [$expiration->isToday() ? 'Сдать сегодня' : 'Сдать скоро', 'clock', 'pending'],
        default => ['Нет решения', 'circle', 'neutral'],
    };
@endphp
<span {{ $attributes->class(['gc-task-state', 'gc-task-state--'.$tone, 'gc-task-state--compact' => $compact]) }} data-task-state="{{ $task->id }}" @if($outcome) data-workbook-outcome @endif data-task-maximum="{{ $task->max_mark }}" data-task-complete="{{ $task->isFullDone($viewer->id) ? 'true' : 'false' }}" title="{{ $label }}">
    <i class="fas fa-{{ $icon }}" aria-hidden="true"></i><span @class(['visually-hidden' => $compact])>{{ $label }}</span>
</span>
