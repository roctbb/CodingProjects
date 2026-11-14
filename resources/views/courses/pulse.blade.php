@extends('layouts.left-menu')
@section('title', 'Пульс')
@section('content')
    @php
        $pulseSeries = collect($pulse['series'] ?? []);
        $pulseSearch = $pulseSearch ?? '';
        $selectedPulseCourse = $selectedPulseCourse ?? null;
        $pulseFiltered = request()->filled('course') || request()->filled('type') || $pulseSearch !== '';
        $pulseContext = $selectedPulseCourse?->name ?? (request()->filled('course') ? 'Курс недоступен' : 'Все мои курсы');
        $feedTitle = $activityGroups[request('type')]['label'] ?? 'Все события';
        $pulseChange = (int) ($pulse['change'] ?? 0);
    @endphp
    <div class="gc-feed-workspace gc-stack">
        <div class="gc-page-masthead">
            <x-gc-page-header title="Пульс" mark="bolt" description="Учёба, достижения и новости ваших курсов.">
                <a class="btn btn-outline-secondary btn-sm d-none d-md-inline-flex" href="{{ url('/insider/courses') }}">Мои курсы</a>
            </x-gc-page-header>
            <div class="gc-pulse-summary">
                <div class="min-width-0"><span class="gc-help-text">{{ $pulseContext }} · сейчас</span><div class="gc-pulse-summary__value"><strong>{{ $pulse['current'] ?? 0 }}</strong><span>{{ $pulse['label'] ?? 'нет сигнала' }}</span></div><span class="gc-help-text">{{ $pulse['trend'] ?? 'ровно' }}@if($pulseChange !== 0) {{ $pulseChange > 0 ? '+' : '' }}{{ $pulseChange }}@endif за 6 часов</span></div>
                <div class="gc-pulse-summary__chart">
                    <x-gc-pulse-chart :pulse="$pulse" :filled="true" :label="'Активность за 24 часа: '.$pulseContext" />
                    <div class="gc-summary-line gc-help-text"><span>{{ $pulseSeries->first()['label'] ?? '' }}</span><span>24 часа · все типы событий</span><span>{{ $pulseSeries->last()['label'] ?? '' }}</span></div>
                </div>
            </div>
        </div>
        <x-gc-workspace-toolbar class="mb-0">
            <form method="GET" class="gc-toolbar w-100" role="search" aria-label="Фильтры событий">
                <input type="hidden" name="type" value="{{ request('type') }}">
                <div class="input-group gc-search-box gc-toolbar__search"><span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span><input type="search" class="form-control" name="q" value="{{ $pulseSearch }}" maxlength="200" placeholder="Участник, курс или задание" aria-label="Участник, курс или задание"></div>
                <label class="gc-workspace-field gc-toolbar__select"><span>Курс</span><select name="course" class="form-select form-select-sm"><option value="">Все мои курсы</option>
                    @if(request()->filled('course') && !$selectedPulseCourse)
                        <option value="{{ request('course') }}" selected>Курс недоступен</option>
                    @endif
                    @foreach($pulseCourses as $item)<option value="{{ $item->id }}" @selected((string)request('course') === (string)$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                <button class="btn btn-outline-secondary btn-sm" type="submit">Найти</button>
                @if($pulseFiltered)<a class="gc-text-action" href="{{ url('/insider/pulse') }}">Сбросить</a>@endif
            </form>
            <nav class="gc-filters gc-filters--scroll" aria-label="Виды событий">
                @foreach(['' => ['label' => 'Все события']] + $activityGroups as $key => $group)
                    <a class="gc-filter {{ (request('type') ?? '') === $key ? 'is-active' : '' }}" href="{{ url('/insider/pulse').'?'.http_build_query(array_filter(['course' => request('course'), 'q' => $pulseSearch, 'type' => $key], fn ($value) => filled($value))) }}" @if((request('type') ?? '') === $key) aria-current="page" @endif>{{ $group['label'] }}</a>
                @endforeach
            </nav>
        </x-gc-workspace-toolbar>
        <section class="gc-card pulse-feed" aria-label="Лента событий">
            <header class="gc-panel-heading"><div><h2>{{ $feedTitle }}</h2><span class="gc-help-text">{{ $activities->total() }} событий@if($pulseSearch !== '') · поиск «{{ $pulseSearch }}»@endif</span></div><x-gc-page-navigation :paginator="$activities" label="Страницы событий" /></header>
        <div class="pulse-feed__list">
            @forelse($activities as $activity)
                @if($loop->first || $activities[$loop->index - 1]->created_at->toDateString() !== $activity->created_at->toDateString())
                    <h2 class="gc-feed-day">{{ $activity->created_at->isToday() ? 'Сегодня' : ($activity->created_at->isYesterday() ? 'Вчера' : $activity->created_at->translatedFormat('j F, l')) }}</h2>
                @endif
                @php
                    $subtitle = $activity->subtitle();
                    $summary = $activity->payload['summary'] ?? ($activity->payload['achievement_description'] ?? null);
                @endphp
                <a class="pulse-feed__item {{ $activity->toneClass() }}"
                   href="{{ $activity->url() }}"
                   title="{{ trim($activity->title().' '.$subtitle) }}">
                    @include('courses.partials.pulse-icon', ['activity' => $activity])
                    <span class="pulse-feed__body min-width-0">
                        <span class="pulse-feed__title">
                            @if($activity->hasActor())
                                <span class="pulse-feed__actor">{{ $activity->actorName() }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $activity->user, 'compact' => true])
                                <span class="pulse-feed__action">{{ $activity->actionText() }}</span>
                            @else
                                {{ $activity->title() }}
                            @endif
                        </span>
                        @if($subtitle || $activity->course)
                            <span class="pulse-feed__meta">
                                @if($activity->course && empty($activity->payload['course_name']))
                                    <span class="gc-feed-course">{{ $activity->course->name }}</span>
                                    @if($subtitle)
                                        <span aria-hidden="true"> · </span>
                                    @endif
                                @endif
                                {{ $subtitle }}
                            </span>
                        @endif
                        @if($summary)
                            <span class="pulse-feed__news">{{ $summary }}</span>
                        @endif
                    </span>
                    <time class="pulse-feed__time" @if($activity->created_at) datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ $activity->created_at->format('d.m.Y H:i') }}" @endif>{{ $activity->created_at?->format('H:i') }}</time>
                </a>
            @empty
                <p class="gc-help-text p-3 mb-0">{{ $pulseFiltered ? 'Событий по этим фильтрам нет.' : 'Лента оживёт, когда появятся сдачи, проверки и покупки.' }}</p>@if($pulseFiltered)<a class="btn btn-outline-primary btn-sm m-3 mt-0" href="{{ url('/insider/pulse') }}">Сбросить фильтры</a>@endif
            @endforelse
        </div>


            @if($activities->hasPages())<footer class="gc-panel-body gc-summary-line"><span class="gc-help-text">{{ $activities->firstItem() }}–{{ $activities->lastItem() }} из {{ $activities->total() }}</span><x-gc-page-navigation :paginator="$activities" label="Следующие страницы событий" /></footer>@endif
        </section>
    </div>
@endsection
