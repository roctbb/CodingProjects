<div class="dropdown gc-course-actions" data-course-actions>
    <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с курсом" title="Действия с курсом">
        <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end gc-course-actions__menu">
        <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/edit') }}"><i class="fas fa-pen" aria-hidden="true"></i> Изменить курс</a>
        <a class="dropdown-item" href="{{ route('courses.notifications', $course->id) }}"><i class="fab fa-telegram" aria-hidden="true"></i> Уведомления курса</a>
        <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/export-md') }}"><i class="fas fa-file-export" aria-hidden="true"></i> Экспорт в MD</a>
        <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/blocked') }}"><i class="fas fa-ban" aria-hidden="true"></i> Заблокированные задачи</a>
        @if($course->state === 'draft')
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/start') }}"><i class="fas fa-play" aria-hidden="true"></i> Запустить курс</a>
        @elseif($course->state === 'started')
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/stop') }}"><i class="fas fa-stop" aria-hidden="true"></i> Завершить курс</a>
        @endif
    </div>
</div>
