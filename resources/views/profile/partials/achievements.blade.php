<x-gc-panel title="Достижения" icon="fas fa-trophy" id="achievements" class="gc-reward-shelf">
    <x-slot:actions><span class="gc-meta-label">{{ $achievements->count() }}</span></x-slot:actions>
    @if($achievements->isEmpty())
        <p class="text-muted mb-0">Здесь появятся достижения за учебу и проекты.</p>
    @else
    <div class="gc-rows">
        @foreach($achievements->take(3) as $achievement)
            @include('profile.partials.achievement')
        @endforeach

    </div>
    @if($achievements->count() > 3)
        <details class="gc-disclosure gc-disclosure--inline" id="profile-more-achievements">
            <summary>Ещё {{ $achievements->count() - 3 }} достижений</summary>
            <div class="gc-rows">@foreach($achievements->slice(3) as $achievement)@include('profile.partials.achievement')@endforeach</div>
        </details>
    @endif
    @endif
</x-gc-panel>
