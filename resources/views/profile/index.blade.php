@extends('layouts.left-menu')

@section('title')
    Сообщество
@endsection

@section('content')
    @php
        $visibleUsers = $users->reject->is_hidden->sortByDesc(function ($user) { return $user->score(); });
        $topUsers = $visibleUsers->take(3);
    @endphp

    <div class="community-hero mb-4">
        <div>
            <span class="community-hero__eyebrow">GeekClass</span>
            <h2 class="mb-2">Сообщество</h2>
            <p class="mb-0">Участники клуба, их роли, ранги и прогресс в обучении.</p>
        </div>
        <div class="community-hero__stats">
            <div>
                <strong>{{ $visibleUsers->count() }}</strong>
                <span>участников</span>
            </div>
            <div>
                <strong>{{ $visibleUsers->where('is_teacher', true)->count() }}</strong>
                <span>преподавателей</span>
            </div>
        </div>
    </div>

    @if($topUsers->count())
        <div class="community-leaders mb-4">
            @foreach($topUsers as $leader)
                <a href="{{ url('/insider/profile/'.$leader->id) }}" class="community-leader-card gc-card text-decoration-none">
                    <span class="community-leader-card__place">#{{ $loop->iteration }}</span>
                    <img src="{{ $leader->imageUrl() }}" class="avatar xl" alt="">
                    <div class="min-width-0">
                        <h6 class="mb-1 text-truncate">{{ $leader->name }}</h6>
                        <span class="badge rounded-pill bg-success"><i class="fas fa-arrow-up me-1"></i>{{ $leader->rank()->name }}</span>
                        <small class="d-block text-muted mt-1">{{ $leader->score() }} XP</small>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="community-grid">
        @foreach($visibleUsers as $user)
            <a href="{{ url('/insider/profile/'.$user->id) }}" class="community-member-card gc-card text-decoration-none">
                <div class="community-member-card__avatar">
                    <img src="{{ $user->imageUrl() }}" class="avatar lg" alt="">
                </div>
                <div class="community-member-card__body">
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <h6 class="mb-1 text-truncate">{{ $user->name }}</h6>
                        <span class="community-member-card__score">{{ $user->score() }} XP</span>
                    </div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge rounded-pill bg-success">
                            <i class="fas fa-arrow-up me-1"></i>{{ $user->rank()->name }}
                        </span>
                        @if ($user->is_trainee)
                            <span class="badge rounded-pill bg-info">Стажер</span>
                        @endif
                        @if ($user->is_teacher)
                            <span class="badge rounded-pill bg-info">Преподаватель</span>
                        @endif
                    </div>
                    <x-gc-progress :percent="100*($user->score()-$user->rank()->from)/($user->rank()->to-$user->rank()->from)" height="5px" />
                </div>
            </a>
        @endforeach
    </div>
@endsection
