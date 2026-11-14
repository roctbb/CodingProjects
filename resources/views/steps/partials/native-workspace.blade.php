<section class="gc-card p-3 my-3 gc-execution-workspace"
    data-execution-workspace
    data-endpoint="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id) }}"
    data-teacher="{{ $nativeTeacher ? 'true' : 'false' }}"
    data-attempt-price="{{ Auth::user()->geekPasteExtraAttemptCost() }}"
    data-blocked="{{ $task->isBlocked(Auth::id(), $course->id) ? 'true' : 'false' }}"
    @isset($nativeUser) data-user="{{ $nativeUser }}" @endisset
    @isset($nativeSubmission) data-submission="{{ $nativeSubmission }}" @endisset
    aria-label="Сдача и проверка решения">
    <p class="text-muted mb-0" role="status">Загружаем решения…</p>
    <noscript>Для сдачи файлов и просмотра хода проверки нужен JavaScript.</noscript>
</section>
