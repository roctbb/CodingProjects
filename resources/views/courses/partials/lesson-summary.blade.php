@php
    $stepCount = $lesson->steps->count();
    $stepCountLabel = $stepCount % 100 >= 11 && $stepCount % 100 <= 14 ? 'ступеней'
        : match ($stepCount % 10) { 1 => 'ступень', 2, 3, 4 => 'ступени', default => 'ступеней' };
    $lessonMaximum = $lesson->steps->flatMap->tasks
        ->filter(fn ($task) => $isManager || $task->isVisible($user, $course))->sum('max_mark');
    $compactMeta = [];
    $showGroupProgress = $isManager && (!$showTeacherLock || $lessonCard['hasActivity']);
    if ($isLearner && $isAvailable) {
        if ($lessonView['workLabel']) $compactMeta[] = $lessonView['workLabel'];
        if ($lessonView['pendingCount']) $compactMeta[] = $lessonView['pendingCount'].' на проверке';
        if ($lessonView['checkingCount']) $compactMeta[] = 'Автопроверка: '.$lessonView['checkingCount'];
    } elseif ($showGroupProgress && $lessonTaskCount > 0 && $lessonCard['total']) {
        $compactMeta[] = 'Завершили: '.$lessonCard['completed'].' из '.$lessonCard['total'];
        if (!$lessonCard['pending'] && $lessonCard['checking']) $compactMeta[] = 'Автопроверка: '.$lessonCard['checking'];
        elseif (!$lessonCard['pending'] && $lessonCard['notStarted']) $compactMeta[] = 'Без сдач: '.$lessonCard['notStarted'];
    } elseif ($isManager) {
        $compactMeta[] = $lessonTaskCount ? \App\Support\LessonTaskProgress::taskCount($lessonTaskCount) : 'Материалы для чтения';
    } elseif (!$isCurrent) {
        $compactMeta[] = $stepCount.' '.$stepCountLabel;
    }
    $compactLocked = !$isAvailable || $showTeacherLock;
    $compactEarlyUnlock = $compactLocked && $lesson->early_access_enabled;
    $compactLockHint = $compactEarlyUnlock ? 'Урок не открыт. Можно открыть раньше общего расписания.' : 'Урок не открыт';
    $compactProgress = $showGroupProgress && $hasLessonStatistics && $lessonCard['assessed'] ? $lessonCard['percent']
        : ($isLearner && $isAvailable && $cmaxPoints > 0 ? $cprogressWidth : null);
    $compactProgressLabel = $isManager ? 'Ученики, завершившие урок' : 'Личный результат';
    $compactProgressHint = $showGroupProgress && $hasLessonStatistics && $lessonCard['assessed']
        ? 'Завершили урок: '.$lessonCard['completed'].' из '.$lessonCard['total'].'. Все обязательные задачи зачтены. Наклейку получили: '.$lessonSummary['rewarded']
        : ($compactProgress !== null ? $cpoints.' / '.$cmaxPoints.' XP · '.$compactProgress.'%' : 'Урок '.($pathIndex + 1));
    if ($hasEarlyAccess) $compactProgressHint = 'Вы открыли урок раньше остальных. '.$compactProgressHint;
@endphp
<div class="course-lesson-compact">
    <span class="course-lesson-compact__number @if($hasEarlyAccess) course-lesson-compact__number--early-access @endif" title="{{ $compactProgressHint }}"
          @if($compactProgress !== null)
              role="progressbar" aria-label="Урок {{ $pathIndex + 1 }}. {{ $compactProgressLabel }}" aria-valuenow="{{ $compactProgress }}" aria-valuemin="0" aria-valuemax="100" aria-valuetext="{{ $compactProgressHint }}"
          @else
              role="img" aria-label="{{ $compactProgressHint }}"
          @endif>
        <svg viewBox="0 0 44 44" aria-hidden="true" focusable="false">
            <circle class="course-lesson-compact__ring-track" cx="22" cy="22" r="19" />
            @if($compactProgress > 0)
                <circle class="course-lesson-compact__ring-value" cx="22" cy="22" r="19" pathLength="100" stroke-dasharray="{{ $compactProgress }} 100" />
            @endif
        </svg>
        <span aria-hidden="true">{{ $pathIndex + 1 }}</span>
    </span>
    <div class="course-lesson-compact__body">
        <h3 class="course-lesson-compact__title" data-filter-by="text">
            @if($isAvailable)
                <a href="{{ $lessonUrl }}" @if($isCurrent) aria-current="step" @endif>{{ $lesson->name }}</a>
            @else
                {{ $lesson->name }}
            @endif
        </h3>
        @if($compactMeta || $compactLocked || ($isManager && $lessonCard['pending']))
            <div class="course-lesson-compact__meta">
                @if($compactMeta)<span>{{ implode(' · ', $compactMeta) }}</span>@endif
                @if($compactLocked)
                    <span class="course-lesson-compact__state @if($compactEarlyUnlock) course-lesson-compact__state--early-access @endif" role="img" aria-label="{{ $compactLockHint }}" title="{{ $compactLockHint }}"><i class="fas fa-lock" aria-hidden="true"></i></span>
                @endif
                @if($isManager && $lessonCard['pending'])<a class="course-lesson-review-link" href="{{ $lessonCard['reviewUrl'] }}" data-lesson-review-link>На проверке: {{ $lessonCard['pending'] }}</a>@endif
                @if($isLearner && $isAvailable && $cmaxPoints > 0)<span class="course-lesson-compact__xp">{{ $cpoints }} / {{ $cmaxPoints }} XP</span>@endif
            </div>
        @endif
        <div class="course-lesson-compact__access">@include('courses.partials.lesson-access')</div>
    </div>
    <div class="course-lesson-compact__controls">
        <div class="course-path-meta">@include('courses.partials.lesson-deadline')</div>
        @include('courses.partials.lesson-utilities')
        @include('courses.partials.lesson-actions')
    </div>
    @if($showLessonSticker)
        <x-gc-lesson-reward :lesson="$lesson" :course="$course" sticker :status="$rewardStatus" class="course-lesson-compact__reward" />
    @endif
</div>
