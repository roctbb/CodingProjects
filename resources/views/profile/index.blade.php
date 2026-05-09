@extends('layouts.left-menu')

@section('title')
    Сообщество
@endsection

@section('content')
    @php
        $visibleUsers = $users->reject->is_hidden->sortByDesc(function ($user) { return $user->score(); });
        $topUsers = $visibleUsers->take(3);
    @endphp

    <div class="community-hero gc-card gc-page-header mb-4 overflow-hidden">
        <div>
            <span class="gc-eyebrow mb-2">GeekClass</span>
            <h2 class="mb-2">Сообщество</h2>
            <p class="mb-0 text-muted col-lg-8">Участники клуба, их роли, ранги и прогресс в обучении.</p>
        </div>
        <div class="row row-cols-2 g-2 flex-nowrap community-hero-stats">
            <div class="col">
                <div class="community-stat">
                    <strong>{{ $visibleUsers->count() }}</strong>
                    <span>участников</span>
                </div>
            </div>
            <div class="col">
                <div class="community-stat">
                    <strong>{{ $visibleUsers->where('is_teacher', true)->count() }}</strong>
                    <span>преподавателей</span>
                </div>
            </div>
        </div>
    </div>

    @if($topUsers->count())
        <section class="mb-4">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                <h5 class="mb-0">Лидеры по опыту</h5>
                <span class="text-muted small">топ {{ $topUsers->count() }}</span>
            </div>
            <div class="row row-cols-1 row-cols-md-3 g-3 community-leaders">
                @foreach($topUsers as $leader)
                    <div class="col">
                        <a href="{{ url('/insider/profile/'.$leader->id) }}" class="community-card community-leader-card gc-card">
                            <span class="community-rank-badge gc-soft-badge position-absolute top-0 end-0 mt-2 me-2">#{{ $loop->iteration }}</span>
                            <x-gc-avatar :user="$leader" size="xl" class="flex-shrink-0" alt="" />
                            <div class="min-width-0 pe-4">
                                <h6 class="mb-1 text-truncate">{{ $leader->name }}</h6>
                                <div class="d-flex flex-wrap gap-1">
                                    @include('profile.partials.custom_title_badge', ['profileUser' => $leader, 'compact' => true])
                                    <span class="gc-soft-badge"><i class="fas fa-arrow-up me-1"></i>{{ $leader->rank()->name }}</span>
                                </div>
                                <small class="d-block text-muted mt-1">{{ $leader->score() }} XP</small>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="gc-card gc-toolbar-card community-toolbar">
        <div>
            <h5 class="mb-1">Участники</h5>
            <p class="mb-0 text-muted small">Быстрый поиск по имени, роли, рангу и опыту.</p>
        </div>
        <div class="input-group input-group-sm gc-search-box community-search">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="search" class="form-control" placeholder="Найти участника" aria-label="Найти участника" data-community-search data-community-grid="#community-grid">
            <button class="btn d-none" type="button" data-community-clear aria-label="Очистить поиск"><i class="fas fa-times"></i></button>
            <span class="input-group-text gc-search-box__count" data-community-count>{{ $visibleUsers->count() }} из {{ $visibleUsers->count() }}</span>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-4 g-3" id="community-grid" data-community-grid>
        @foreach($visibleUsers as $user)
            @php $activeCustomTitle = $user->activeCustomTitle(); @endphp
            <div class="col" data-community-user data-community-user-text="{{ $user->name }} {{ $activeCustomTitle }} {{ $user->rank()->name }} {{ $user->score() }} XP @if($user->is_trainee) стажер @endif @if($user->is_teacher) преподаватель @endif">
                <a href="{{ url('/insider/profile/'.$user->id) }}" class="community-card community-user-card gc-card">
                    <x-gc-avatar :user="$user" size="lg" class="flex-shrink-0" alt="" />
                    <div class="min-width-0 w-100">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <h6 class="mb-1 text-truncate">{{ $user->name }}</h6>
                            <span class="gc-meta-label flex-shrink-0 mt-1">{{ $user->score() }} XP</span>
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-2 community-user-card__badges">
                            @if ($activeCustomTitle)
                                @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => true])
                            @endif
                            <span class="gc-soft-badge">
                                <i class="fas fa-arrow-up me-1"></i>{{ $user->rank()->name }}
                            </span>
                            @if ($user->is_trainee)
                                <span class="gc-soft-badge">Стажер</span>
                            @endif
                            @if ($user->is_teacher)
                                <span class="gc-soft-badge">Преподаватель</span>
                            @endif
                        </div>
                        <x-gc-progress :percent="100*($user->score()-$user->rank()->from)/($user->rank()->to-$user->rank()->from)" height="5px" />
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
