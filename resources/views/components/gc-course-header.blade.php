@props(['course', 'active' => 'program', 'chapter' => null])

@php
    $viewer = Auth::user();
    $canManage = $viewer && ($viewer->role === 'admin' || ($viewer->role === 'teacher' && $course->teachers->contains('id', $viewer->id)));
    $participantScope = \App\Support\CourseScope::for($course, $viewer);
    $courseGroups = app(\App\Services\CourseSubgroups::class)->available() ? $course->subgroups : collect();
    $contextChapter = $chapter instanceof \App\ProgramChapter ? $chapter->id : ($chapter ?? request('chapter'));
    $contextChapter = is_scalar($contextChapter) && ctype_digit((string) $contextChapter) && (int) $contextChapter > 0 ? (int) $contextChapter : null;
    if ($contextChapter && !$course->program?->chapters->contains('id', $contextChapter)) $contextChapter = null;
    $sections = ['program' => ['Программа', 'fa-book-open', url('/insider/courses/'.$course->id)]];
    if (config('course_planner.enabled') && ($canManage || $viewer?->role === 'student')) {
        $sections['plan'] = ['План занятий', 'fa-calendar-days', route('courses.planner', $course->id)];
    }
    if ($canManage) {
        $sections['journal'] = ['Журнал', 'fa-table', url('/insider/courses/'.$course->id.'/assessments')];
        $sections['report'] = ['Отчёт', 'fa-chart-line', url('/insider/courses/'.$course->id.'/report')];
        if ($active === 'subgroups') $sections['subgroups'] = ['Подгруппы', 'fa-user-group', route('courses.subgroups', $course->id)];
    }
    $sections['deadlines'] = ['Дедлайны', 'fa-flag', url('/insider/courses/'.$course->id.'/deadlines')];
    if ($viewer?->role === 'student') {
        $sections['tasks'] = ['Задачи главы', 'fa-list-check', route('courses.chapter-tasks', $course->id)];
    }
@endphp

<div class="gc-course-context gc-page-masthead" data-course-context data-course-id="{{ $course->id }}" data-course-scope="{{ $participantScope->manager ? $participantScope->key : '' }}" data-course-chapter="{{ $contextChapter }}">
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
    <div class="gc-course-footer {{ $courseGroups->isNotEmpty() ? 'gc-course-footer--scoped' : '' }}">
    <x-gc-tab-strip>
    <nav class="gc-page-tabs gc-course-navigation" aria-label="Разделы курса" data-course-navigation>
        @foreach($sections as $key => [$label, $icon, $href])
            <a class="nav-link {{ $active === $key ? 'active' : '' }}" href="{{ $participantScope->url($href.($contextChapter ? '?chapter='.$contextChapter : '')) }}" @if($active === $key) aria-current="page" @endif>
                <i class="fas {{ $icon }}" aria-hidden="true"></i>{{ $label }}
            </a>
        @endforeach
    </nav>
    </x-gc-tab-strip>
    @if($courseGroups->isNotEmpty())
        @if($canManage)
            <form method="GET" class="gc-course-scope" aria-label="Состав курса">
                @foreach(request()->except(['subgroup', 'student', 'page', 'report_detail']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                <label class="visually-hidden" for="course-subgroup">Участники курса</label>
                <select class="form-select form-select-sm gc-view-select" id="course-subgroup" name="subgroup" onchange="this.form.submit()"><option value="all" @selected($participantScope->key === 'all')>Весь класс</option>@foreach($courseGroups as $group)<option value="{{ $group->id }}" @selected($participantScope->group?->id === $group->id)>{{ $group->name }}</option>@endforeach<option value="none" @selected($participantScope->key === 'none')>Без подгруппы</option></select>
                <noscript><button class="btn btn-outline-secondary btn-sm">Показать</button></noscript>
            </form>
        @else
            <p class="gc-course-scope gc-help-text mb-0"><i class="fas fa-user-group me-1" aria-hidden="true"></i>{{ $participantScope->label() }}</p>
        @endif
    @endif
    </div>
</div>
