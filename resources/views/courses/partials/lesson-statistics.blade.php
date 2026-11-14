<div class="course-lesson-stats__summary">
    <div><span>Средний прогресс</span><strong>{{ $summary['average'] }}%</strong></div>
    <div><span>Наклейку получили</span><strong>{{ $summary['rewarded'] }} / {{ $summary['total'] }}</strong></div>
    <div><span>Участников</span><strong>{{ $summary['total'] }}</strong></div>
</div>
<div class="course-lesson-stats__rows">
    @foreach($students as $student)
        @php
            $row = $stats->get($student->id);
            $percent = min(100, max(0, (float) ($row->percent ?? 0)));
        @endphp
        <div class="course-stat-row small">
            <span class="course-stat-name">{{ $student->name }}</span>
            <span class="course-stat-mini"><span class="course-stat-mini__bar" data-progress-width="{{ $percent }}%"></span><span class="course-stat-mini__value">{{ $row->points ?? 0 }} / {{ $row->max_points ?? 0 }} XP</span></span>
        </div>
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
