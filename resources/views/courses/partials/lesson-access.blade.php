@if ($startDate != null && $startDate->isFuture())
    <small class="course-lesson-date"><i class="fas fa-clock" aria-hidden="true"></i> {{ $hasEarlyAccess ? 'Для всех с' : 'Откроется' }} {{$startDate->translatedFormat('j F Y')}}</small>
@endif


@if ($canBuyEarlyAccess)
    @if ($canAffordEarlyAccess)
        <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/lessons/'.$lesson->id.'/early-access') }}" class="m-0">
            @csrf
            <button type="submit" class="btn btn-sm solution-special-action course-early-access-action"
                    data-confirm="{{ $earlyAccessCost > 0 ? 'Купить ранний доступ к этому уроку за '.$earlyAccessCost.' GC?' : 'Открыть этот урок бесплатно с помощью Брр Брр Потапим?' }}">
                <i class="fas fa-key" aria-hidden="true"></i>
                @if ($earlyAccessCost > 0)
                    Открыть раньше за {{ $earlyAccessCost }} GC
                @else
                    Открыть раньше бесплатно
                @endif
            </button>
        </form>
    @else
        <small class="course-early-access-note"><i class="fas fa-key" aria-hidden="true"></i> Открыть раньше: {{ $earlyAccessCost }} GC</small>
    @endif
@endif
