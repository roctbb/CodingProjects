<section class="course-lesson-stats course-lesson-compact-stats course-lesson-personal-stats d-none" id="marks{{ $lesson->id }}" data-course-stats-panel data-course-stats-loaded="true" aria-label="Моя статистика урока «{{ $lesson->name }}»" aria-live="polite">
    <div class="course-lesson-personal-stats__summary">
        <div><span>Мои баллы</span><strong>{{ $cpoints }}@if($cmaxPoints > 0) / {{ $cmaxPoints }}@endif XP</strong></div>
        @if($cmaxPoints > 0)<div><span>Прогресс</span><strong>{{ $cprogressWidth }}%</strong></div>@endif
        @if($showLessonSticker)<div><span>Наклейка</span><strong>{{ $rewardStatus === 'earned' ? 'Получена' : 'Ещё не получена' }}</strong></div>@endif
    </div>
    <div class="course-lesson-task-results" aria-label="Мои задачи">
        @foreach($lessonView['rows'] as $row)
            @php
                $task = $row['task'];
                $statusLabel = match ($row['status']) {
                    'done' => 'Решена', 'pending' => 'На проверке', 'revision' => 'Нужны исправления',
                    'checking' => 'Проверяется автоматически', 'check_error' => 'Проверка не завершилась',
                    'improve' => 'Можно улучшить', default => 'Не сдана',
                };
                $taskUrl = app(\App\Services\TaskLocation::class)->task($course->id, $task, $user->id);
            @endphp
            <article class="course-lesson-task-result" data-personal-task="{{ $task->id }}" data-task-status="{{ $row['status'] }}">
                <div class="course-lesson-task-result__heading">
                    <a href="{{ $taskUrl }}">{{ $task->name }}</a>
                    <span class="course-lesson-task-result__score">
                        @if($row['evaluation'] === null)
                            Без оценки
                        @else
                            {{ $row['points'] }} / {{ $row['maximum'] }} XP
                        @endif
                    </span>
                </div>
                <div class="course-lesson-task-result__meta"><span>{{ $statusLabel }}</span>@if($row['optional'])<span>Дополнительная задача</span>@endif</div>
                @if($row['evaluation'] !== null && $row['evaluation'] != $row['points'])<p class="course-lesson-task-result__feedback"><span>Оценка проверки: {{ $row['evaluation'] }} / {{ $row['maximum'] }}. Баллы XP учитывают сроки и бонусы.</span></p>@endif
                @if($row['feedback'])<p class="course-lesson-task-result__feedback"><span>Последняя проверка: </span>{{ \Illuminate\Support\Str::limit(strip_tags($row['feedback']), 400) }}</p>@endif
                @if(!$row['feedback'] && ($row['feedbackAvailable'] ?? false))<p class="course-lesson-task-result__feedback"><a class="gc-quiet-link" href="{{ $taskUrl }}">Открыть результат проверки</a></p>@endif
            </article>
        @endforeach
    </div>
</section>
