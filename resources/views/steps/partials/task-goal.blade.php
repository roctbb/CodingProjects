@if(filled($step->lesson?->sticker) && ($taskLessonProgress->max_points ?? 0) > 0)
    @php($lessonRewardEarned = ($taskLessonProgress->percent ?? 0) > 90)
    <div class="gc-task-goal" data-task-lesson-progress data-lesson-id="{{ $step->lesson_id }}">
        <x-gc-lesson-reward :lesson="$step->lesson" :course="$course" sticker :status="$lessonRewardEarned ? 'earned' : 'preview'" />
        <div>
            <span class="gc-help-text" data-lesson-goal-label>{{ $lessonRewardEarned ? 'Наклейка в коллекции' : 'Следующая цель — наклейка урока' }}</span>
            <strong data-lesson-goal-value>{{ min(100, max(0, round($taskLessonProgress->percent))) }}% урока</strong>
            <progress max="100" value="{{ min(100, max(0, $taskLessonProgress->percent)) }}" aria-label="Прогресс урока"></progress>
        </div>
    </div>
@endif
