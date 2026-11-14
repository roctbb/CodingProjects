@props(['course', 'active' => 'program', 'chapter' => null])

@php
    $viewer = Auth::user();
    $canManage = $viewer && ($viewer->role === 'admin' || ($viewer->role === 'teacher' && $course->teachers->contains('id', $viewer->id)));
    $contextChapter = $chapter instanceof \App\ProgramChapter ? $chapter->id : ($chapter ?? request('chapter'));
    $contextChapter = is_scalar($contextChapter) && ctype_digit((string) $contextChapter) && (int) $contextChapter > 0 ? (int) $contextChapter : null;
    if ($contextChapter && !$course->program?->chapters->contains('id', $contextChapter)) $contextChapter = null;
    $sections = ['program' => ['Программа', 'fa-book-open', url('/insider/courses/'.$course->id)]];
    if ($canManage) {
        if (config('course_planner.enabled')) {
            $sections['plan'] = ['План занятий', 'fa-calendar-days', route('courses.planner', $course->id)];
        }
        $sections['journal'] = ['Журнал', 'fa-table', url('/insider/courses/'.$course->id.'/assessments')];
        $sections['report'] = ['Отчёт', 'fa-chart-line', url('/insider/courses/'.$course->id.'/report')];
    }
    $sections['deadlines'] = ['Дедлайны', 'fa-flag', url('/insider/courses/'.$course->id.'/deadlines')];
    if ($viewer?->role === 'student') {
        $sections['tasks'] = ['Задачи главы', 'fa-list-check', route('courses.chapter-tasks', $course->id)];
    }
@endphp

<div class="gc-course-context gc-page-masthead" data-course-context data-course-chapter="{{ $contextChapter }}">
    <x-gc-page-header :title="$course->name" :description="$canManage ? null : $course->description">
        <x-slot:breadcrumb><a class="assessment-back-link" href="{{ url('/insider/courses') }}">← Мои курсы</a></x-slot:breadcrumb>
        <x-slot:icon><x-gc-course-icon :course="$course" /></x-slot:icon>
        @if($course->state === 'ended')
            <x-slot:summary>
                <span class="gc-course-ended-label"><i class="fas fa-circle-check" aria-hidden="true"></i> Курс завершён · материалы для повторения</span>
            </x-slot:summary>
        @endif
        @if($canManage)
            @include('courses.partials.course-actions')
        @endif
    </x-gc-page-header>
    <x-gc-tab-strip>
    <nav class="gc-page-tabs gc-course-navigation" aria-label="Разделы курса" data-course-navigation>
        @foreach($sections as $key => [$label, $icon, $href])
            <a class="nav-link {{ $active === $key ? 'active' : '' }}" href="{{ $href.($contextChapter ? '?chapter='.$contextChapter : '') }}" @if($active === $key) aria-current="page" @endif>
                <i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}
            </a>
        @endforeach
    </nav>
    </x-gc-tab-strip>
</div>
