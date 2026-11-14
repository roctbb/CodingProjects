@php
    $reviewFilters = $reviewFilters ?? ['q' => '', 'review_status' => 'all'];
    $reviewCourseValue = isset($reviewQueueAll) && !$reviewQueueAll ? $course->id : request('course');
    $reviewFiltered = filled($reviewCourseValue) || $reviewFilters['q'] !== '' || $reviewFilters['review_status'] !== 'all';
@endphp
<div class="{{ ($embedded ?? false) ? 'gc-review-filters' : 'gc-workspace-toolbar mb-0' }}">
    <form method="GET" action="{{ url('/insider/reviews') }}" class="gc-toolbar w-100" role="search" aria-label="Найти работу">
        <div class="input-group gc-search-box gc-toolbar__search"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span><input type="search" class="form-control" name="q" value="{{ $reviewFilters['q'] }}" maxlength="200" placeholder="Ученик или задание" aria-label="Ученик или задание"></div>
        <label class="gc-workspace-field gc-toolbar__select"><span>Курс</span><select class="form-select form-select-sm" name="course"><option value="">Все мои курсы</option>
            @if(filled($reviewCourseValue) && !$reviewCourses->contains(fn ($item) => (string) $item->id === (string) $reviewCourseValue))
                <option value="{{ $reviewCourseValue }}" selected>Курс вне активной очереди</option>
            @endif
            @foreach($reviewCourses as $item)<option value="{{ $item->id }}" @selected((string)$reviewCourseValue === (string)$item->id)>{{ $item->name }}</option>@endforeach</select></label>
        <label class="gc-workspace-field gc-toolbar__select"><span>Сдачи</span><select class="form-select form-select-sm" name="review_status"><option value="all" @selected($reviewFilters['review_status'] === 'all')>Все непроверенные</option><option value="new" @selected($reviewFilters['review_status'] === 'new')>Новые</option><option value="recheck" @selected($reviewFilters['review_status'] === 'recheck')>Перепроверка</option></select></label>
        <button class="btn btn-outline-secondary btn-sm" type="submit">Найти</button>
        @if($reviewFiltered)<a class="gc-text-action" href="{{ url('/insider/reviews') }}">Сбросить</a>@endif
    </form>
</div>
