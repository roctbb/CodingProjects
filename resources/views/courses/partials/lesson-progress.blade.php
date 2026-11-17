@php
    $interactiveProgress = $isLearner && $isAvailable && $compactProgress !== null;
    $progressPopoverId = 'lesson-progress-'.$course->id.'-'.$lesson->id;
@endphp
@if($interactiveProgress)
    <div class="course-lesson-compact__progress" data-lesson-progress-popover>
    <button type="button" class="course-lesson-compact__number @if($hasEarlyAccess) course-lesson-compact__number--early-access @endif"
            data-lesson-progress-trigger popovertarget="{{ $progressPopoverId }}" aria-controls="{{ $progressPopoverId }}" aria-expanded="false"
            aria-label="Урок {{ $pathIndex + 1 }}. Прогресс {{ $compactProgress }}%. Показать подробности"
            @if($hasEarlyAccess) title="Вы открыли урок раньше остальных." @endif>
@else
    <span class="course-lesson-compact__number @if($hasEarlyAccess) course-lesson-compact__number--early-access @endif" title="{{ $compactProgressHint }}"
          @if($compactProgress !== null)
              role="progressbar" aria-label="Урок {{ $pathIndex + 1 }}. {{ $compactProgressLabel }}" aria-valuenow="{{ $compactProgress }}" aria-valuemin="0" aria-valuemax="100" aria-valuetext="{{ $compactProgressHint }}"
          @else
              role="img" aria-label="{{ $compactProgressHint }}"
          @endif>
@endif
        <svg viewBox="0 0 44 44" aria-hidden="true" focusable="false">
            <circle class="course-lesson-compact__ring-track" cx="22" cy="22" r="19" />
            @if($compactProgress > 0)
                <circle class="course-lesson-compact__ring-value" cx="22" cy="22" r="19" pathLength="100" stroke-dasharray="{{ $compactProgress }} 100" />
            @endif
        </svg>
        <span aria-hidden="true">
            @if($compactLocked)
                <i class="fas fa-lock course-lesson-compact__state @if($compactEarlyUnlock) course-lesson-compact__state--early-access @endif"></i>
            @else
                <span class="course-lesson-compact__index">{{ $pathIndex + 1 }}</span>
                @if($interactiveProgress)<span class="course-lesson-compact__percent">{{ $compactProgress }}%</span>@endif
            @endif
        </span>
@if($interactiveProgress)
    </button>
    <div id="{{ $progressPopoverId }}" class="gc-lesson-progress-popover__panel" popover role="region" aria-label="Прогресс урока «{{ $lesson->name }}»">
        <div class="gc-lesson-progress-popover__heading">
            <strong>Прогресс урока</strong>
            <button type="button" class="btn-options" popovertarget="{{ $progressPopoverId }}" popovertargetaction="hide" aria-label="Закрыть прогресс урока"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>
        <p class="gc-help-text mb-0">{{ $lesson->name }}</p>
        @if($hasEarlyAccess)<p class="gc-help-text mt-2 mb-0">Вы открыли урок раньше остальных.</p>@endif
        <div class="course-lesson-progress__score"><strong>{{ $cpoints }} / {{ $cmaxPoints }} XP</strong><span>{{ $compactProgress }}%</span></div>
        @php
            $progressRows = $lessonView['rows']->where('optional', false);
            $progressLabels = ['done' => 'Решены полностью', 'improve' => 'Можно улучшить', 'revision' => 'Нужны исправления',
                'pending' => 'На проверке', 'checking' => 'Автопроверка', 'check_error' => 'Ошибка проверки', 'todo' => 'Не сданы'];
        @endphp
        @if($progressRows->isNotEmpty())
            <p class="gc-help-text mt-2 mb-0">{{ \App\Support\LessonTaskProgress::taskCount($progressRows->count()) }} в основной части урока</p>
        @endif
        <dl class="course-lesson-progress__details">
            @foreach($progressLabels as $progressStatus => $progressLabel)
                @php($progressCount = $progressRows->where('status', $progressStatus)->count())
                @if($progressCount)<dt>{{ $progressLabel }}</dt><dd>{{ $progressCount }}</dd>@endif
            @endforeach
            @if($showLessonSticker)<dt>Наклейка</dt><dd>{{ $rewardStatus === 'earned' ? 'Получена' : 'Ещё не получена' }}</dd>@endif
        </dl>
    </div>
    </div>
@else
    </span>
@endif
