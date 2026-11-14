@extends('layouts.left-menu')
@section('title', 'Проверка')
@section('content')
    @php
        $reviewFiltered = request()->filled('course') || $reviewFilters['q'] !== '' || $reviewFilters['review_status'] !== 'all';
    @endphp
    <div class="solution-review-page gc-stack">
        <div class="gc-page-masthead"><x-gc-page-header title="Проверка" mark="code-branch" description="Сдачи учеников и запросы на перепроверку."><a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/courses') }}">Мои курсы</a></x-gc-page-header></div>
        @include('steps.partials.review-filters')
        @if($pendingSolutions->isNotEmpty())
            <section class="gc-card" aria-labelledby="pending-review-heading">
                <div class="gc-panel-heading"><h2 class="h6 mb-0" id="pending-review-heading">Нужно проверить</h2><span class="gc-meta-label ms-auto">{{ $pendingSolutions->total() }}</span></div>
                <div class="table-responsive"><table class="table align-middle mb-0">
                    <thead><tr><th>Ученик</th><th>Задача</th><th>Курс</th><th>Сдано</th><th><span class="visually-hidden">Действие</span></th></tr></thead>
                    <tbody>@foreach($pendingSolutions as $pending)
                        @php($taskUrl = app(\App\Services\TaskLocation::class)->solution($pending))
                        <tr data-review-queue-solution="{{ $pending->id }}">
                            <td><a href="{{ $taskUrl }}">{{ $pending->user->name }}</a></td>
                            <td><a href="{{ $taskUrl }}">{{ $pending->task->name }}</a>@if($pending->recheck_requested)<span class="gc-task-state gc-task-state--pending ms-2">Перепроверка</span>@endif</td>
                            <td>{{ $pending->course->name }}</td><td class="text-nowrap">{{ $pending->submitted?->format('d.m.Y H:i') }}</td>
                            <td><a class="btn btn-outline-primary btn-sm text-nowrap" href="{{ $taskUrl }}">Открыть работу</a></td>
                        </tr>
                    @endforeach</tbody>
                </table></div>
                @if($pendingSolutions->hasPages())<div class="gc-panel-body"><x-gc-page-navigation :paginator="$pendingSolutions" label="Страницы очереди" /></div>@endif
                <div class="gc-panel-body">
                    <form method="post" action="{{ url('/insider/reviews/reset-pending') }}" data-confirm="Пропустить все {{ $pendingSolutions->total() }} непроверенных решений, соответствующих фильтрам?">
                        @csrf
                        @if(request()->filled('course'))<input type="hidden" name="course" value="{{ request()->integer('course') }}">@endif
                        <input type="hidden" name="q" value="{{ $reviewFilters['q'] }}"><input type="hidden" name="review_status" value="{{ $reviewFilters['review_status'] }}">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">Пропустить список · {{ $pendingSolutions->total() }}</button>
                    </form>
                </div>
            </section>
        @else
        <section class="gc-card gc-empty-state">
            <div class="gc-empty-icon"><i class="fas fa-check" aria-hidden="true"></i></div>
            <h2 class="h6">{{ $reviewFiltered ? 'По этим фильтрам работ нет' : 'Все работы проверены' }}</h2>
            <p class="mb-3">{{ $reviewFiltered ? 'Измените запрос или выберите другой курс.' : 'Новые сдачи и запросы на перепроверку появятся здесь.' }}</p>
            @if($reviewFiltered)<a class="btn btn-outline-primary btn-sm" href="{{ url('/insider/reviews') }}">Сбросить фильтры</a>@endif
        </section>
        @endif
    </div>
@endsection
