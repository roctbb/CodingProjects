<div class="course-lesson-stats__summary">
    <div><span>Средний результат</span><strong>{{ $summary['average'] }}%</strong></div>
    <div><span>Наклейку получили</span><strong><i class="fas fa-award course-stat-reward" aria-hidden="true"></i> {{ $summary['rewarded'] }} / {{ $summary['total'] }}</strong></div>
    <div><span>Участников</span><strong>{{ $summary['total'] }}</strong></div>
</div>
<div class="course-lesson-stats__rows">
    @foreach($students as $student)
        @php
            $row = $stats->get($student->id);
            $percent = min(100, max(0, (float) ($row->percent ?? 0)));
        @endphp
        <a class="course-stat-row small"
           href="{{ url('/insider/courses/'.$course->id.'/report').'?'.http_build_query(['chapter' => $lesson->chapter_id, 'student' => $student->id, 'lesson' => $lesson->id]).'#student'.$student->id }}"
           aria-label="{{ $student->name }}: отчёт по уроку «{{ $lesson->name }}»">
            <span class="course-stat-name">{{ $student->name }}@if($percent > 90)<i class="fas fa-award course-stat-reward" aria-label="Наклейка получена"></i>@endif</span>
            <span class="course-stat-score">{{ $row->points ?? 0 }} / {{ $row->max_points ?? 0 }} XP</span>
            <span class="course-stat-track" aria-hidden="true"><span data-progress-width="{{ $percent }}%"></span></span>
            <span class="course-stat-percent">{{ round($percent) }}%</span>
        </a>
    @endforeach
</div>
<div class="gc-course-stats-navigation">
    <span class="gc-help-text">{{ $students->firstItem() ?? 0 }}–{{ $students->lastItem() ?? 0 }} из {{ $students->total() }}</span>
    @if($students->hasPages())
        <button type="button" class="btn btn-outline-secondary btn-sm" data-course-stats-page="{{ $students->currentPage() - 1 }}" @disabled($students->onFirstPage()) aria-label="Предыдущие ученики">←</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-course-stats-page="{{ $students->currentPage() + 1 }}" @disabled(!$students->hasMorePages()) aria-label="Следующие ученики">→</button>
    @endif
    <a class="gc-quiet-link small" href="{{ url('/insider/courses/'.$course->id.'/report?chapter='.$lesson->chapter_id) }}">Отчёт по главе</a>
</div>
