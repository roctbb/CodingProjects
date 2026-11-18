@if ($isManager)
    @php
        $lessonScope = \App\Support\CourseScope::for($course);
        $markedCurrentLessonId = $lessonScope->group?->current_lesson_override ? $lessonScope->group->current_lesson_id : $course->current_lesson_id;
    @endphp
    <div class="course-lesson-actions course-path-actions">
        <div class="dropdown">
            <button class="btn btn-sm btn-options" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Действия с уроком {{ $lesson->name }}">
                <i class="fas fa-ellipsis-h" aria-hidden="true"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-end">
                <form method="POST" action="{{ route('courses.current-lesson', $course->id) }}">
                    @csrf
                    @include('courses.partials.scope-input')
                    <input type="hidden" name="lesson_id" value="{{ $markedCurrentLessonId == $lesson->id ? '' : $lesson->id }}">
                    <input type="hidden" name="chapter" value="{{ $chapter->id }}">
                    <button type="submit" class="dropdown-item"><i class="fas fa-thumbtack" aria-hidden="true"></i> {{ $markedCurrentLessonId == $lesson->id ? 'Снять отметку текущего урока' : 'Сделать текущим уроком' }}</button>
                </form>
                @if($lessonScope->group?->current_lesson_override)
                    <form method="POST" action="{{ route('courses.current-lesson', $course->id) }}">@csrf
                        @include('courses.partials.scope-input')<input type="hidden" name="inherit_current" value="1"><input type="hidden" name="chapter" value="{{ $chapter->id }}">
                        <button type="submit" class="dropdown-item"><i class="fas fa-link" aria-hidden="true"></i> Текущий урок как у курса</button>
                    </form>
                @endif
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
@endif
