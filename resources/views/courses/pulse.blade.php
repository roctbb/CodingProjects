@extends('layouts.left-menu')

@section('title', 'Пульс')

@section('content')
    <div class="gc-title-row gc-title-row--center">
        <div class="min-width-0">
            <span class="text-muted text-uppercase fw-bold font-monospace small d-block mb-1">workspace</span>
            <h2 class="mb-1">Пульс</h2>
        </div>

        <a class="btn btn-outline-secondary rounded-3 fw-semibold px-3 py-2" href="{{ url('/insider/courses') }}">
            <i class="fas fa-graduation-cap me-1"></i>Мои курсы
        </a>
    </div>

    <section class="gc-card pulse-feed">
        <div class="pulse-feed__head">
            <div class="d-flex align-items-center gap-2 min-width-0">
                <span class="pulse-feed__head-icon"><i class="fas fa-wave-square"></i></span>
                <h5 class="mb-0">Все события</h5>
            </div>
            <span class="badge rounded-pill bg-body-tertiary">{{ $activities->total() }}</span>
        </div>

        <div class="pulse-feed__list">
            @forelse($activities as $activity)
                @php
                    $activityFrame = $activity->user && method_exists($activity->user, 'activeAvatarFrame')
                        ? $activity->user->activeAvatarFrame()
                        : null;
                    $subtitle = $activity->subtitle();
                @endphp
                <a class="pulse-feed__item {{ $activity->toneClass() }} @if($activityFrame) pulse-feed__item--framed pulse-feed__item--frame-{{ $activityFrame }} @endif"
                   href="{{ $activity->url() }}"
                   title="{{ trim($activity->title().' '.$subtitle) }}">
                    <span class="pulse-feed__icon">
                        <i class="{{ $activity->iconClass() }}"></i>
                    </span>
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
                        @if($subtitle)
                            <span class="pulse-feed__meta">{{ $subtitle }}</span>
                        @endif
                    </span>
                    <span class="pulse-feed__time">
                        <span>{{ $activity->timeAgo() }}</span>
                        @if($activity->created_at)
                            <span>{{ $activity->created_at->format('d.m.Y H:i') }}</span>
                        @endif
                    </span>
                </a>
            @empty
                <div class="pulse-feed__empty">
                    <span class="pulse-feed__icon is-muted"><i class="fas fa-seedling"></i></span>
                    <span>Лента оживёт, когда появятся сдачи, проверки и покупки бустеров.</span>
                </div>
            @endforelse
        </div>

        @if($activities->hasPages())
            <div class="pulse-feed__pagination">
                {{ $activities->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </section>
@endsection
