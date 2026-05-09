<div class="gc-card home-activity mb-4">
    <a class="home-activity__head home-activity__head--link" href="{{ url('/insider/pulse') }}" aria-label="Открыть пульс">
        <div class="d-flex align-items-center gap-2 min-width-0">
            <span class="home-activity__head-icon flex-shrink-0"><i class="fas fa-wave-square"></i></span>
            <div class="min-width-0">
                <h6 class="mb-0">Пульс</h6>
            </div>
        </div>
        <span class="d-inline-flex align-items-center gap-2 flex-shrink-0">
            <span class="badge rounded-pill bg-body-tertiary">{{ $courseActivitiesTotal ?? $courseActivities->count() }}</span>
            <i class="fas fa-arrow-right home-activity__head-arrow"></i>
        </span>
    </a>

    <div class="home-activity__list">
        @forelse($courseActivities as $activity)
            @php
                $activityFrame = $activity->user && method_exists($activity->user, 'activeAvatarFrame')
                    ? $activity->user->activeAvatarFrame()
                    : null;
            @endphp
            <a class="home-activity__item {{ $activity->toneClass() }} @if($activityFrame) home-activity__item--framed home-activity__item--frame-{{ $activityFrame }} @endif"
               href="{{ $activity->url() }}"
               title="{{ trim($activity->title().' '.$activity->subtitle()) }}">
                <span class="home-activity__icon">
                    <i class="{{ $activity->iconClass() }}"></i>
                </span>
                <span class="home-activity__body min-width-0">
                    <span class="home-activity__title">
                        @if($activity->hasActor())
                            <span class="home-activity__actor">{{ $activity->actorName() }}</span>
                            @include('profile.partials.custom_title_badge', ['profileUser' => $activity->user, 'compact' => true])
                            <span class="home-activity__action">{{ $activity->actionText() }}</span>
                        @else
                            {{ $activity->title() }}
                        @endif
                    </span>
                </span>
                <span class="home-activity__time">{{ $activity->timeAgo() }}</span>
            </a>
        @empty
            <div class="home-activity__empty">
                <span class="home-activity__icon is-muted"><i class="fas fa-seedling"></i></span>
                <span class="small text-muted">Лента оживёт, когда появятся сдачи, проверки и покупки бустеров.</span>
            </div>
        @endforelse
    </div>
</div>
