@if(($isManager && ($hasLessonStatistics || $lesson->is_open)) || $hasPersonalStatistics)
    <div class="course-lesson-utility-actions">
        @if($hasLessonStatistics || $hasPersonalStatistics)
            <button type="button" class="btn btn-options course-stats-toggle course-lesson-stat-action" data-course-stats-toggle data-course-stats-target="#marks{{$lesson->id}}" aria-expanded="false" aria-controls="marks{{$lesson->id}}" aria-label="Статистика урока" title="Статистика урока"><i class="fas fa-chart-bar" aria-hidden="true"></i></button>
        @endif
        @if($isManager && $lesson->is_open)
            <a class="btn btn-options course-open-url-action" href="{{ url('/open/steps/'.$lesson->steps->first()->id) }}" target="_blank" rel="noopener" aria-label="Открыть публичную страницу урока «{{ $lesson->name }}»" title="Открытая страница урока"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
        @endif
    </div>
@endif
