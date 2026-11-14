@if($isManager)
    <div class="gc-course-program-actions" data-chapter-actions>
        <a class="btn btn-outline-secondary btn-sm {{ $course->program->chapters->count() > 1 ? 'gc-icon-button' : '' }}" href="{{ url('/insider/courses/'.$course->id.'/chapter') }}" aria-label="Добавить главу" title="Добавить главу"><i class="fas fa-plus" aria-hidden="true"></i>@if($course->program->chapters->count() === 1)<span>Глава</span>@endif</a>
        <div class="dropdown course-current-chapter-actions">
            <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с главой" title="Действия с главой"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
            <div class="dropdown-menu dropdown-menu-end gc-course-actions__menu">
                @if($course->default_chapter_id == $chapter->id)
                    <span class="dropdown-item disabled"><i class="fas fa-bullseye" aria-hidden="true"></i> Открывается по умолчанию</span>
                @else
                    <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/default') }}">
                        @csrf
                        <button type="submit" class="dropdown-item"><i class="fas fa-bullseye" aria-hidden="true"></i> Открывать по умолчанию</button>
                    </form>
                @endif
                <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/edit') }}"><i class="fas fa-pen" aria-hidden="true"></i> Изменить главу</a>
                <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/export-points') }}"><i class="fas fa-file-excel" aria-hidden="true"></i> Экспорт баллов в Excel</a>
                @if($course->program->chapters->count() > 1)
                    <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/lower') }}"><i class="fas fa-arrow-up" aria-hidden="true"></i> Выше</a>
                    <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/chapters/'.$chapter->id.'/upper') }}"><i class="fas fa-arrow-down" aria-hidden="true"></i> Ниже</a>
                @endif
            </div>
        </div>
    </div>
@endif
