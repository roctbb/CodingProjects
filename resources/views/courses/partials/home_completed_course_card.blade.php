<div class="col">
    @php
        $completedIcon = $completedCourse->course
            ? \App\Support\CourseIcons::forCourse($completedCourse->course)
            : \App\Support\CourseIcons::resolve(null, (string) $completedCourse->name);
    @endphp
    <article class="course-index-card gc-card">
    @if($completedCourse->course && $completedCourse->is_linked)
        <a href="{{ url('insider/courses/'.$completedCourse->course_id) }}" class="course-index-card__body gc-card-link">
    @else
        <div class="course-index-card__body">
    @endif
        <div class="course-index-card__content">
            <x-gc-course-icon :name="$completedIcon" class="course-index-card__icon course-index-card__icon--{{ $completedIcon }}" />
            <div class="course-index-card__head">
                <h6 class="course-index-card__title">{{ $completedCourse->name }}</h6>
                @if($completedCourse->mark)
                    <span class="badge rounded-pill bg-body-tertiary flex-shrink-0" aria-label="Отметка за курс: {{ $completedCourse->mark }}">{{ $completedCourse->mark }}</span>
                @endif
            </div>
            <p class="course-index-card__description">Курс завершён</p>
        </div>
    @if($completedCourse->course && $completedCourse->is_linked)
        </a>
    @else
        </div>
    @endif
    </article>
</div>
