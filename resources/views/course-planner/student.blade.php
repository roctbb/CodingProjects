@extends('layouts.left-menu')
@section('title', 'План занятий — '.$course->name)
@section('content')
<x-gc-course-header :course="$course" active="plan" />
@php
    $from = \Carbon\CarbonImmutable::parse($data['range']['from']);
    $to = \Carbon\CarbonImmutable::parse($data['range']['to']);
    $scope = \App\Support\CourseScope::for($course);
    $slots = collect($data['slots'])->keyBy('id');
    $sessions = collect($data['sessions'])->filter(fn($s) => $s['calendar_slot_id'] && $slots->has($s['calendar_slot_id']))
        ->sortBy(fn($s) => $slots[$s['calendar_slot_id']]['local_date'].' '.($slots[$s['calendar_slot_id']]['start_time'] ?? ''));
@endphp
<div class="gc-stack">
    <x-gc-workspace-toolbar>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a class="btn btn-outline-secondary btn-sm" aria-label="Предыдущая неделя" href="{{ request()->fullUrlWithQuery(['from'=>$from->subWeek()->toDateString(), 'to'=>$to->subWeek()->toDateString()]) }}">←</a>
            <strong>{{ $from->format('d.m') }} — {{ $to->format('d.m.Y') }}</strong>
            <a class="btn btn-outline-secondary btn-sm" aria-label="Следующая неделя" href="{{ request()->fullUrlWithQuery(['from'=>$from->addWeek()->toDateString(), 'to'=>$to->addWeek()->toDateString()]) }}">→</a>
            <a class="gc-text-action" href="{{ route('courses.planner', $course->id) }}">Сегодня</a>
            <span class="gc-help-text">{{ $data['settings']['timezone'] === 'Europe/Moscow' ? 'Московское время' : $data['settings']['timezone'] }}</span>
        </div>
    </x-gc-workspace-toolbar>
    @forelse($sessions as $session)
        @php($slot = $slots[$session['calendar_slot_id']])
        <section class="gc-card p-3 p-md-4 gc-stack gc-stack--compact">
            <div><p class="gc-help-text mb-1">{{ \Carbon\Carbon::parse($slot['local_date'])->translatedFormat('l, j F') }}@if($slot['start_time']) · {{ $slot['start_time'] }}@endif · {{ $slot['duration_minutes'] }} мин@if($session['audience_name'] && $scope->group?->id !== $session['audience_id']) · {{ $session['audience_id'] ? $session['audience_name'] : 'Общее занятие' }}@endif</p><h2 class="gc-section-title">{{ $session['title'] }}</h2></div>
            @if($session['plan_note'])<p class="mb-0">{{ $session['plan_note'] }}</p>@endif
            @foreach($session['entries'] as $entry)
                @if($entry['kind'] === 'text')<p class="mb-0">{{ $entry['text'] }}</p>
                @else<div>@if($entry['resource']['open_url'] ?? null)<a class="gc-text-action" href="{{ $entry['resource']['open_url'] }}">{{ $entry['resource']['title'] }}</a>@else{{ $entry['resource']['title'] }}@endif
                @if($entry['deadline']['due_on'] ?? null)<small class="gc-help-text d-block">Срок: {{ \Carbon\Carbon::parse($entry['deadline']['due_on'])->format('d.m.Y') }}</small>@endif</div>@endif
            @endforeach
        </section>
    @empty<div class="gc-card p-4"><h2 class="gc-section-title">На этой неделе занятий нет</h2><p class="gc-help-text mb-0">Выберите другую неделю.</p></div>@endforelse
</div>
@endsection
