@php
    $sortedSolutions = $task->solutions
        ->where('user_id', Auth::user()->id)
        ->sortByDesc('submitted');
@endphp

@foreach ($sortedSolutions as $solution)
    @include('steps.solution_partial')
@endforeach
