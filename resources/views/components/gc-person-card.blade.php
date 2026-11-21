@props(['user', 'position' => null, 'profileQuery' => []])

@php
    $character = $user->roomCharacterImageUrl() ? $user->roomCharacterGeneration : null;
    $isTeacher = $user->is_teacher || in_array($user->role, ['teacher', 'admin']);
    $href = url('/insider/profile/'.$user->id).($profileQuery ? '?'.http_build_query($profileQuery) : '');
@endphp
<a {{ $attributes->class(['gc-card', 'gc-person-card']) }} href="{{ $href }}" id="community-user-{{ $user->id }}" data-community-user>
    <div class="gc-person-card__portrait">
        @if($character)
            <x-gc-avatar :user="$user" :src="route('room-character.preview', ['generation_id'=>$character->id, 'portrait'=>1])" size="xl" img-class="gc-person-character" alt="Авторский персонаж" loading="lazy" />
        @else
            <x-gc-avatar :user="$user" size="xl" alt="" loading="lazy" />
        @endif
    </div>
    <div class="gc-person-card__identity">
        <h3>{{ $user->name }} @if($position)<span class="gc-reward-tag" title="Место по опыту среди учеников">@if($position <= 3)<i class="fas fa-medal" aria-hidden="true"></i>@endif#{{ $position }}</span>@endif</h3>
        @if($isTeacher || $user->is_trainee)
            <div class="gc-entry__meta">@if($isTeacher)<span>Преподаватель</span>@endif @if($user->is_trainee)<span>Стажёр</span>@endif</div>
        @endif
        <div class="gc-person-card__summary"><span>{{ $user->score() }} XP</span>@if($user->community_stickers)<span>· {{ trans_choice(':count наклейка|:count наклейки|:count наклеек', $user->community_stickers, [], 'ru') }}</span>@endif</div>
        @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => true])
    </div>
</a>
