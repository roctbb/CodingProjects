@props(['user', 'subtitle' => null, 'position' => null, 'current' => false])

<a {{ $attributes->class(['gc-person-row', 'is-current' => $current])->merge(['title' => $user->name]) }} href="{{ url('/insider/profile/'.$user->id) }}">
    <x-gc-avatar :user="$user" size="md" alt="" />
    <span class="gc-person-row__body">
        <span class="gc-person-row__name">{{ $user->name }}</span>
        @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => true])
        @if($subtitle !== null)<span class="gc-person-row__meta">{{ $subtitle }}</span>@endif
    </span>
    @if($position !== null)<span class="gc-person-row__position">{{ $position }} место</span>@endif
</a>
