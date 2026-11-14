@php
    $stats = \App\Support\CourseLessonStatistics::forStudents($stats, $students);
    $summary = \App\Support\CourseLessonStatistics::summary($stats, $students->count());
    $statisticsUrl = route('courses.lesson-statistics', [$course->id, $lesson->id]);
    $initialStudents = \App\Support\CourseLessonStatistics::firstPage($students, $statisticsUrl);
@endphp
<div class="course-lesson-stats d-none mt-3" id="marks{{ $lesson->id }}" data-course-stats-panel data-course-stats-loaded="true" data-course-stats-url="{{ $statisticsUrl }}" aria-live="polite">
    @include('courses.partials.lesson-statistics', ['students' => $initialStudents])
</div>
