@php
    $reviewFilters = $reviewFilters ?? ['q' => '', 'review_status' => 'all'];
    $reviewCourseValue = isset($reviewQueueAll, $course) && !$reviewQueueAll ? $course->id : request('course');
    $isRecentReviews = ($reviewView ?? 'queue') === 'recent';
    $reviewFiltered = filled($reviewCourseValue) || $reviewFilters['q'] !== '' || ($isRecentReviews ? ($activityStatus ?? 'all') !== 'all' : $reviewFilters['review_status'] !== 'all') || !empty($reviewFilters['lesson']);
@endphp
<div class="{{ ($embedded ?? false) ? 'gc-review-filters' : 'gc-workspace-toolbar mb-0' }}">
    <form method="GET" action="{{ url('/insider/reviews') }}" class="gc-toolbar gc-review-filter-form w-100" role="search" aria-label="Найти работу">
        @if($isRecentReviews)<input type="hidden" name="view" value="recent">@endif
        @if(!empty($reviewFilters['lesson']))<input type="hidden" name="lesson" value="{{ $reviewFilters['lesson'] }}"><span class="gc-help-text">Урок: {{ $reviewLessonName ?? 'выбранный' }} · <a href="{{ request()->fullUrlWithoutQuery(['lesson', 'page']) }}">Все уроки</a></span>@endif
        <label class="gc-workspace-field gc-toolbar__search"><span>Поиск</span><span class="input-group gc-search-box"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span><input type="search" class="form-control" name="q" value="{{ $reviewFilters['q'] }}" maxlength="200" placeholder="Ученик или задание" aria-label="Ученик или задание"></span></label>
        <label class="gc-workspace-field gc-toolbar__select"><span>Курс</span><select class="form-select" name="course"><option value="">Все мои курсы</option>
            @if(filled($reviewCourseValue) && !$reviewCourses->contains(fn ($item) => (string) $item->id === (string) $reviewCourseValue))
                <option value="{{ $reviewCourseValue }}" selected>{{ $isRecentReviews ? 'Курс недоступен' : 'Курс вне активной очереди' }}</option>
            @endif
            @foreach($reviewCourses as $item)<option value="{{ $item->id }}" @selected((string)$reviewCourseValue === (string)$item->id)>{{ $item->name }}</option>@endforeach</select></label>
        @if($isRecentReviews)
            <label class="gc-workspace-field gc-toolbar__select"><span>Состояние</span><select class="form-select" name="activity_status">@foreach(['all'=>'Все сдачи','pending'=>'Ожидают оценки','checking'=>'Автопроверка','graded'=>'Проверены','error'=>'Ошибка проверки','skipped'=>'Пропущены'] as $key=>$label)<option value="{{ $key }}" @selected(($activityStatus ?? 'all') === $key)>{{ $label }}</option>@endforeach</select></label>
        @else
            <label class="gc-workspace-field gc-toolbar__select"><span>Сдачи</span><select class="form-select" name="review_status"><option value="all" @selected($reviewFilters['review_status'] === 'all')>Все непроверенные</option><option value="new" @selected($reviewFilters['review_status'] === 'new')>Новые</option><option value="recheck" @selected($reviewFilters['review_status'] === 'recheck')>Перепроверка</option></select></label>
        @endif
        <button class="btn btn-outline-secondary" type="submit">Найти</button>
        @if($reviewFiltered || ($isRecentReviews && ($activityStatus ?? 'all') !== 'all'))<a class="gc-text-action" href="{{ url('/insider/reviews').($isRecentReviews ? '?view=recent' : '') }}">Сбросить</a>@endif
    </form>
</div>
