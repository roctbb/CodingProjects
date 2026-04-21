@extends('layouts.left-menu')

@section('title')
    Курсы
@endsection

@section('content')
    @php
        $activeCategories = $categories->where('is_available', true);
        $draftCategories = $categories->where('is_available', false);
    @endphp

    <div class="gx-course-shell gx-course-shell--catalog">
        <section class="card border-0 rounded-4 shadow-sm gx-course-hero">
            <div class="card-body">
                <div class="gx-course-hero-top">
                    <div>
                        <h1 class="gx-course-title">Образовательные направления</h1>
                        <p class="gx-course-description">
                            Выберите направление и продолжите путь от практики к работе.
                        </p>
                    </div>
                    <div class="gx-course-stats">
                        <span class="badge gx-stat-badge">Опубликовано: {{ $activeCategories->count() }}</span>
                        @if (Auth::check() and Auth::user()->role == 'admin')
                            <span class="badge gx-stat-badge">Черновики: {{ $draftCategories->count() }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <div class="gx-course-toolbar ge-page-header">
            <div class="gx-course-toolbar-head">
                <h4 class="gx-course-toolbar-title">Каталог курсов</h4>
                <p class="gx-course-toolbar-subtitle">
                    @if (Auth::check() and Auth::user()->role == 'admin')
                        Навигация по разделам и управление публикацией
                    @else
                        Навигация по образовательным направлениям
                    @endif
                </p>
            </div>
            @if (Auth::check() and Auth::user()->role == 'admin')
                <ul class="nav nav-pills gx-course-tabs" id="coursesTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active"
                                type="button" role="tab" aria-controls="active" aria-selected="true">
                            Активные
                            <span class="gx-course-tab-count">{{ $activeCategories->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft"
                                type="button" role="tab" aria-controls="draft" aria-selected="false">
                            Черновики
                            <span class="gx-course-tab-count">{{ $draftCategories->count() }}</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm gx-course-create-btn" href="{{url('/categories/create/')}}">
                            <i class="icon fa-solid fa-circle-plus"></i>&nbsp;Создать
                        </a>
                    </li>
                </ul>
            @endif
        </div>

        <div class="tab-content gx-course-tabs-content" id="courses">
            <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                @if ($activeCategories->count())
                    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-4 gx-course-grid">
                        @foreach($activeCategories as $category)
                            <div class="col">
                                <a href="{{url('categories/'.$category->id)}}" class="gx-course-card-link">
                                    <article class="card h-100 gx-course-card">
                                        <div class="gx-course-image-wrap">
                                            <img alt="{{ $category->title }}" class="card-img-top gx-course-image"
                                                 src="{{ $category->card_image_url ?: url('images/clip-education.png') }}">
                                        </div>
                                        <div class="card-body gx-course-card-body d-flex flex-column">
                                            <h5 class="card-title gx-course-card-title">{{ $category->title }}</h5>
                                            <p class="gx-course-card-description">
                                                {{ $category->short_description }}
                                            </p>
                                        </div>
                                    </article>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-secondary gx-course-empty" role="alert">
                        Пока нет опубликованных направлений.
                    </div>
                @endif
            </div>

            @if (Auth::check() and Auth::user()->role == 'admin')
                <div class="tab-pane fade" id="draft" role="tabpanel" aria-labelledby="draft-tab">
                    @if ($draftCategories->count())
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-4 gx-course-grid">
                            @forelse($draftCategories as $category)
                                <div class="col">
                                    <a href="{{url('categories/'.$category->id)}}" class="gx-course-card-link">
                                        <article class="card h-100 gx-course-card is-draft">
                                            <div class="gx-course-image-wrap position-relative">
                                                <img alt="{{ $category->title }}" class="card-img-top gx-course-image"
                                                     src="{{ $category->card_image_url ?: url('images/clip-education.png') }}">
                                                <span class="gx-course-status-badge badge text-bg-warning">Черновик</span>
                                            </div>
                                            <div class="card-body gx-course-card-body d-flex flex-column">
                                                <h5 class="card-title gx-course-card-title">{{ $category->title }}</h5>
                                                <p class="gx-course-card-description">
                                                    {{ $category->short_description }}
                                                </p>
                                            </div>
                                        </article>
                                    </a>
                                </div>
                            @empty
                                <div class="alert alert-secondary gx-course-empty" role="alert">
                                    Черновиков пока нет.
                                </div>
                            @endforelse
                        </div>
                    @else
                        <div class="alert alert-secondary gx-course-empty" role="alert">
                            Черновиков пока нет.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection
