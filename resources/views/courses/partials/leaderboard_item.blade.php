@php
    $studentPoints = $student->points ?? 0;
    $studentDisplayPercent = $displayPercent($student->percent ?? 0);
@endphp
<li>
    <x-gc-person-row :user="$student" :subtitle="$studentPoints.' XP'" :position="$studentRank" :current="$student->id == $user->id" title="{{ $student->name }} · {{ $studentPoints }} XP · {{ round($studentDisplayPercent) }}%" />
</li>
