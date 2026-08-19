@php
    $studentPoints = isset($student->points) ? $student->points : 0;
    $studentPercent = isset($student->percent) ? $student->percent : 0;
    $studentDisplayPercent = $displayPercent($studentPercent);
    $studentProgressWidth = (int) round($studentDisplayPercent);
@endphp

<li>
    <a class="course-leaderboard-item @if ($studentRank <= 3) is-top-{{$studentRank}} @endif @if ($student->id == $user->id) is-current-user @endif" href="{{url('/insider/profile/'.$student->id)}}">
        <span class="course-student-rank">{{$studentRank}}</span>
        <x-gc-avatar :user="$student" size="sm" alt="" />
        <span class="course-leaderboard-person min-width-0">
            <strong class="text-truncate">{{$student->name}}</strong>
            <span class="course-leaderboard-meta-row">
                @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                <small class="text-muted text-truncate">{{$studentPoints}} XP</small>
            </span>
        </span>
        @if ($studentProgressWidth > 0)
            <span class="course-student-progress" title="Прогресс: {{ round($studentDisplayPercent) }}%">
                <span class="course-student-progress__bar" data-progress-width="{{ $studentProgressWidth }}%"></span>
                <span class="course-student-progress__value">{{ round($studentDisplayPercent) }}%</span>
            </span>
        @endif
    </a>
</li>
