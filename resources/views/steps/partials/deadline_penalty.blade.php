@php
    $deadlinePenaltyCost = $solution->deadlinePenaltyCost();
    $rawMark = $solution->raw_mark ?: $solution->mark + $solution->deadline_penalty_amount;
    $canPayDeadlinePenalty = Auth::check() && $solution->canPayDeadlinePenalty(Auth::user());
@endphp

@if($solution->deadline_penalty_waived_at)
    <div class="small text-muted mt-2"><i class="fas fa-calendar-check me-1"></i>Штраф снят преподавателем {{ $solution->deadline_penalty_waived_at->format('d.m.Y H:i') }}</div>
@elseif($canPayDeadlinePenalty)
    <form class="solution-special-action-row" method="POST" action="{{ url('/insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/deadline-penalty') }}">
        {{ csrf_field() }}
        <button type="submit" class="btn btn-sm solution-special-action"
                data-confirm="Снять штраф за дедлайн с этого решения за {{ $deadlinePenaltyCost }} GC?">
            <i class="fas fa-calendar-check"></i>
            Снять штраф за {{ $deadlinePenaltyCost }} GC
        </button>
    </form>
@endif
