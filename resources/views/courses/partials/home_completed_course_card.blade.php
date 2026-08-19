<div class="col">
    @if($completedCourse->course && $completedCourse->is_linked)
        <a href="{{ url('insider/courses/'.$completedCourse->course_id) }}" class="gc-card home-completed-course-card">
    @else
        <div class="gc-card home-completed-course-card">
    @endif
        <span class="home-completed-course-card__icon" aria-hidden="true">
            <i class="fas fa-check"></i>
        </span>
        <span class="home-completed-course-card__content">
            <strong>{{ $completedCourse->name }}</strong>
            <small>Курс завершён</small>
        </span>
        @if($completedCourse->mark)
            <span class="badge rounded-pill bg-body-tertiary home-completed-course-card__mark">{{ $completedCourse->mark }}</span>
        @endif
    @if($completedCourse->course && $completedCourse->is_linked)
        </a>
    @else
        </div>
    @endif
</div>
