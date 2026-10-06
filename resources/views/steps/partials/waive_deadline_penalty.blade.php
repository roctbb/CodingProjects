@if($solution->deadline_penalty_waived_at)
    <div class="small text-muted mt-2"><i class="fas fa-calendar-check me-1"></i>Штраф снят преподавателем {{ $solution->deadline_penalty_waived_at->format('d.m.Y H:i') }}</div>
@elseif($solution->mark !== null && $solution->hasActiveDeadlinePenalty() && Auth::check()
    && (Auth::user()->role === 'admin' || (Auth::user()->role === 'teacher' && $course->teachers->contains('id', Auth::id()))))
    <form class="solution-special-action-row" method="POST" action="{{ url('/insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/waive-deadline-penalty') }}">
        @csrf
        <button type="submit" class="btn btn-sm solution-special-action" data-confirm="Убрать штраф за дедлайн и восстановить XP без списания GC у ученика?">
            <i class="fas fa-calendar-check"></i>
            Убрать штраф
        </button>
    </form>
@endif
