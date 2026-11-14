<div class="step-screen-actions" aria-label="Управление ступенью">
    <a class="btn btn-primary btn-sm" href="{{ url('/insider/courses/'.$course->id.'/perform/'.$step->id) }}"><i class="fas fa-desktop" aria-hidden="true"></i> Занятие</a>
    <a class="btn btn-outline-secondary btn-sm gc-icon-button" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/edit') }}" aria-label="Редактировать ступень" title="Редактировать ступень"><i class="fas fa-pen" aria-hidden="true"></i></a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/task/create') }}"><i class="fas fa-plus me-1" aria-hidden="true"></i>Задание</a>
    <div class="dropdown">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия со ступенью"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
        <div class="dropdown-menu dropdown-menu-end">
            @if($prevUrl)
                <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/lower') }}" class="dropdown-item"><i class="fas fa-arrow-up" aria-hidden="true"></i> Поднять ступень</a>
            @endif
            @if($nextUrl)
                <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/upper') }}" class="dropdown-item"><i class="fas fa-arrow-down" aria-hidden="true"></i> Опустить ступень</a>
            @endif
            <a href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id.'/delete') }}" class="dropdown-item text-danger" data-confirm="Вы уверены?"><i class="fas fa-xmark" aria-hidden="true"></i> Удалить ступень</a>
        </div>
    </div>
</div>
