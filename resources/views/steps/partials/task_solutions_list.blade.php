@php
    $sortedSolutions = $task->solutions
        ->where('user_id', Auth::user()->id)
        ->sortByDesc('submitted');
@endphp

@if($sortedSolutions->isNotEmpty())
    @include('steps.solution_partial', ['solution' => $sortedSolutions->first()])
    @if($sortedSolutions->count() > 1)
        <details class="gc-disclosure gc-disclosure--inline gc-solution-history">
            <summary>Предыдущие попытки · {{ $sortedSolutions->count() - 1 }}</summary>
            @foreach($sortedSolutions->skip(1) as $solution)
                @include('steps.solution_partial')
            @endforeach
        </details>
    @endif
@endif
