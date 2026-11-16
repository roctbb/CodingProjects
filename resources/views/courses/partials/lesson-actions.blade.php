@if ($isManager)
    <div class="course-lesson-actions course-path-actions">
        <div class="dropdown">
            <button class="btn btn-sm btn-options" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Действия с уроком {{ $lesson->name }}">
                <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end">
                <form method="POST" action="{{ route('courses.current-lesson', $course->id) }}">
                    @csrf
                    <input type="hidden" name="lesson_id" value="{{ $course->current_lesson_id == $lesson->id ? '' : $lesson->id }}">
                    <input type="hidden" name="chapter" value="{{ $chapter->id }}">
                    <button type="submit" class="dropdown-item"><i class="fas fa-thumbtack" aria-hidden="true"></i> {{ $course->current_lesson_id == $lesson->id ? 'Снять отметку текущего урока' : 'Сделать текущим уроком' }}</button>
                </form>
                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/edit')}}" class="dropdown-item"><i class="fas fa-pen" aria-hidden="true"></i> Изменить</a>
                @if ($lessonTaskCount > 0)
                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lesson-deadline-modal-{{$lesson->id}}"><i class="fas fa-calendar-days" aria-hidden="true"></i> Дедлайн задач</button>
                @endif
                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export')}}" class="dropdown-item"><i class="fas fa-download" aria-hidden="true"></i> Экспорт</a>
                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-points')}}" class="dropdown-item"><i class="fas fa-chart-column" aria-hidden="true"></i> Экспорт баллов</a>
                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/export-md')}}" class="dropdown-item"><i class="fas fa-file-export" aria-hidden="true"></i> Экспорт в MD</a>
                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/lower?chapter='.$chapter->id)}}" class="dropdown-item"><i class="fas fa-arrow-up" aria-hidden="true"></i> Выше</a>
                <a href="{{url('insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/upper?chapter='.$chapter->id)}}" class="dropdown-item"><i class="fas fa-arrow-down" aria-hidden="true"></i> Ниже</a>
            </div>
        </div>
    </div>
@elseif($isLearner)
    <div class="course-lesson-actions course-path-actions">
        <div class="dropdown">
            <button class="btn btn-sm btn-options" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false" aria-label="Действия с уроком {{ $lesson->name }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
            <div class="dropdown-menu dropdown-menu-end">
                @if($isAvailable)
                    <a class="dropdown-item" href="{{ $lessonUrl }}" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i> Открыть урок в новой вкладке</a>
                @endif
                <button type="button" class="dropdown-item" data-copy-text="{{ url('/insider/courses/'.$course->id.'?chapter='.$lesson->chapter_id.'#lesson-'.$lesson->id) }}" aria-label="Скопировать ссылку на урок {{ $lesson->name }}"><i class="fas fa-copy" aria-hidden="true"></i> Скопировать ссылку</button>
                <span class="visually-hidden" role="status" data-copy-status></span>
            </div>
        </div>
    </div>
@endif
