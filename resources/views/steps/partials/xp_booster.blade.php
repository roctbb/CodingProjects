@php
    $xpBoosterCost = Auth::check() ? $solution->xpBoosterCost(Auth::user()) : $solution->xpBoosterCost();
    $canUseXpBooster = Auth::check() && $solution->canUseXpBooster(Auth::user());
@endphp

@if($canUseXpBooster)
    <form class="solution-special-action-row" method="POST" action="{{ url('/insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/xp-booster') }}">
        {{ csrf_field() }}
        <button type="submit" class="btn btn-sm solution-special-action"
                data-confirm="Применить бустер +5 XP к этому решению{{ $xpBoosterCost > 0 ? ' за '.$xpBoosterCost.' GC' : ' бесплатно' }}?">
            <i class="fas fa-wand-magic-sparkles"></i>
            @if($xpBoosterCost > 0)
                Использовать бустер за {{ $xpBoosterCost }} GC
            @else
                Использовать бесплатный бустер
            @endif
        </button>
    </form>
@endif
