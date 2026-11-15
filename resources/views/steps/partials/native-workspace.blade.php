<section class="my-3 gc-execution-workspace"
    id="{{ $nativeWorkspaceId ?? 'execution-workspace-'.$task->id }}"
    data-execution-workspace
    @if($nativeTabWorkspace ?? false) data-execution-tab-workspace @endif
    @if($nativeTeacher && !isset($nativeUser)) data-execution-inline-review hidden @endif
    data-endpoint="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id) }}"
    @php($nativeStep = $step ?? $task->step)
    @php($continueTask = isset($tasks) ? $tasks->values()->get(($loop->index ?? 0) + 1) : null)
    data-continue-url="{{ $continueTask ? url('/insider/courses/'.$course->id.'/steps/'.$nativeStep->id.'#task'.$continueTask->id) : url('/insider/courses/'.$course->id.'?chapter='.$nativeStep->lesson->chapter_id) }}"
    data-continue-label="{{ $continueTask ? 'Следующая задача' : 'К урокам курса' }}"
    data-task-id="{{ $task->id }}" data-course-id="{{ $course->id }}"
    @if($nativeHistoryOnly ?? false) data-history-only @endif
    data-teacher="{{ $nativeTeacher ? 'true' : 'false' }}"
    data-viewer-id="{{ Auth::id() }}"
    data-attempt-price="{{ Auth::user()->geekPasteExtraAttemptCost() }}"
    data-blocked="{{ $task->isBlocked(Auth::id(), $course->id) ? 'true' : 'false' }}"
    @isset($nativeUser) data-user="{{ $nativeUser }}" @endisset
    @isset($nativeSubmission) data-submission="{{ $nativeSubmission }}" @endisset
    aria-label="Сдача и проверка решения">
    <p class="text-muted mb-0" role="status">{{ $nativeTeacher && !isset($nativeUser) ? 'Выберите ученика или сдайте своё решение.' : 'Загружаем рабочую область…' }}</p>
    <noscript>Для сдачи файлов и просмотра хода проверки нужен JavaScript.</noscript>
</section>
