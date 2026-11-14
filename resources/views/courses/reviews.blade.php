@extends('layouts.left-menu')
@section('title', 'Проверка')
@section('content')
    @php
        $reviewFiltered = request()->filled('course') || $reviewFilters['q'] !== '' || $reviewFilters['review_status'] !== 'all';
    @endphp
    <div class="solution-review-page gc-stack">
        <div class="gc-page-masthead"><x-gc-page-header title="Проверка" mark="code-branch" description="Сдачи учеников и запросы на перепроверку."><a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/courses') }}">Мои курсы</a></x-gc-page-header></div>
        @include('steps.partials.review-filters')
        <section class="gc-card gc-empty-state">
            <div class="gc-empty-icon"><i class="fas fa-check" aria-hidden="true"></i></div>
            <h2 class="h6">{{ $reviewFiltered ? 'По этим фильтрам работ нет' : 'Все работы проверены' }}</h2>
            <p class="mb-3">{{ $reviewFiltered ? 'Измените запрос или выберите другой курс.' : 'Новые сдачи и запросы на перепроверку появятся здесь.' }}</p>
            @if($reviewFiltered)<a class="btn btn-outline-primary btn-sm" href="{{ url('/insider/reviews') }}">Сбросить фильтры</a>@endif
        </section>
    </div>
@endsection
