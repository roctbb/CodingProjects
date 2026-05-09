@php
    $xpBoosterCost = $solution->xpBoosterCost();
    $canUseXpBooster = Auth::check() && $solution->canUseXpBooster(Auth::user());
@endphp

@if($canUseXpBooster)
    <form class="solution-special-action-row" method="POST" action="{{ url('/insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/xp-booster') }}">
        {{ csrf_field() }}
        <button type="submit" class="btn btn-sm solution-special-action"
                data-confirm="Применить бустер +5 XP к этому решению за {{ $xpBoosterCost }} GC?">
            <i class="fas fa-wand-magic-sparkles"></i>
            Использовать бустер за {{ $xpBoosterCost }} GC
        </button>
    </form>
@endif
