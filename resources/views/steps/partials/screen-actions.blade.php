<div class="step-screen-actions" aria-label="Управление ступенью">
    @php($lessonViewUrl = url('/insider/courses/'.$course->id.'/perform/'.$step->id))
    @if(empty($attemptTask))
        <a class="btn btn-primary btn-sm" href="{{ $lessonViewUrl }}"><i class="fas fa-desktop" aria-hidden="true"></i> Занятие</a>
    @endif
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия со ступенью"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
        <div class="dropdown-menu dropdown-menu-end">
            @if(!empty($attemptTask))
                <a class="dropdown-item" href="{{ $lessonViewUrl }}"><i class="fas fa-desktop" aria-hidden="true"></i> Режим занятия</a>
            @endif
            <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit') }}"><i class="fas fa-pen" aria-hidden="true"></i> Редактировать ступень</a>
            <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/task/create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Добавить задание</a>
            @if(!$hasStepNavigation)
                <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/lessons/'.$step->lesson_id.'/create') }}"><i class="fas fa-plus" aria-hidden="true"></i> Добавить ступень</a>
            @endif
            @if($step->is_notebook && filled($step->theory))
                <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/notebook') }}" download><i class="fas fa-download" aria-hidden="true"></i> Скачать .ipynb</a>
            @endif
            @if($prevUrl)
                <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower') }}" class="dropdown-item"><i class="fas fa-arrow-up" aria-hidden="true"></i> Поднять ступень</a>
            @endif
            @if($nextUrl)
                <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper') }}" class="dropdown-item"><i class="fas fa-arrow-down" aria-hidden="true"></i> Опустить ступень</a>
            @endif
            <hr class="dropdown-divider">
            <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete') }}" class="dropdown-item text-danger" data-confirm="Вы уверены?"><i class="fas fa-xmark" aria-hidden="true"></i> Удалить ступень</a>
        </div>
    </div>
</div>
