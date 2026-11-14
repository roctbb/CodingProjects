@props(['user', 'position' => null, 'profileQuery' => []])

@php
    $rank = $user->rank();
    $isTeacher = $user->is_teacher || in_array($user->role, ['teacher', 'admin']);
    $href = url('/insider/profile/'.$user->id).($profileQuery ? '?'.http_build_query($profileQuery) : '');
@endphp
<a {{ $attributes->class(['gc-card', 'gc-person-card']) }} href="{{ $href }}" id="community-user-{{ $user->id }}" data-community-user>
    <div class="gc-person-card__portrait"><x-gc-avatar :user="$user" size="xl" alt="" loading="lazy" /></div>
    <div class="gc-person-card__identity">
        <h2>{{ $user->name }} @if($position)<span class="gc-reward-tag" title="Место по опыту среди учеников">@if($position <= 3)<i class="fas fa-medal" aria-hidden="true"></i>@endif#{{ $position }}</span>@endif</h2>
        @if($isTeacher || $user->is_trainee)
            <div class="gc-entry__meta">@if($isTeacher)<span>Преподаватель</span>@endif @if($user->is_trainee)<span>Стажёр</span>@endif</div>
        @endif
        <div class="gc-person-card__summary"><span class="gc-rank-label">{{ $rank->name }}</span><span class="gc-help-text">{{ $user->score() }} XP</span></div>
        @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => true])
    </div>
</a>
