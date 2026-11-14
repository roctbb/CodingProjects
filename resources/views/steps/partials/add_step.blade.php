@if(\Request::is('insider/*') && isset($course) && ($course->teachers->contains($user) || $user->role == 'admin'))
    <a href="{{ url('/insider/courses/'.$course->id.'/lessons/'.$step->lesson->id.'/create') }}"
       class="btn btn-outline-secondary btn-sm gc-workbook-add-step @if($compact ?? false) gc-icon-button @endif" aria-label="Добавить ступень в урок" title="Добавить ступень">
        <i class="fas fa-plus" aria-hidden="true"></i><span @if($compact ?? false) class="visually-hidden" @endif>Добавить ступень</span>
    </a>
@endif
