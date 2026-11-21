@extends('layouts.left-menu')
@section('title', $user->name)
@section('content')
    @php
        $rank = $user->rank();
        $score = $user->score();
        $progressPercent = max(0, min(100, 100 * ($score - $rank->from) / max(1, $rank->to - $rank->from)));
        $isOwner = $guest->id == $user->id;
        $hasLongName = mb_strlen($user->name) > 36 || collect(preg_split('/\s+/u', trim($user->name)))->contains(fn ($word) => mb_strlen($word) > 10);
        $managedCourses = $user->managed_courses->where('state', 'started');
        $startedCourses = $user->courses->where('state', 'started');
        $completedCourses = app(\App\Queries\StudentCompletedCourses::class)->forStudent($user);
        $hasAbout = filled($user->school) || $user->grade_year !== null || $user->hasVisibleBirthday() || filled($user->interests) || filled($user->hobbies);
        $hasTeacherComment = in_array($guest->role, ['teacher', 'admin']) && filled($user->comments);
        $learningAvatarData = $learningAvatarData ?? $user->learningAvatarRenderData();
        $learningAvatarConfig = $learningAvatarData['config'];
        $learningAvatarOwned = $learningAvatarConfig['owned'] ?? [];
        $learningAvatarOptionsBySlot = $learningAvatarData['optionsBySlot'];
        $learningAvatarPreviewItemsBySlot = $learningAvatarData['previewItemsBySlot'];
        $currentLearningAvatarManifest = $learningAvatarConfig['manifest'] ?? 'room-system';
        $learningAvatarSlotLabels = ['desk_center' => 'Стол', 'pet_right' => 'Питомец'];
    @endphp
    <div class="gc-stack" data-profile-sections>
        <section class="gc-card gc-profile-hero" aria-label="Профиль участника">
            <div class="gc-profile-hero__identity">
                <x-gc-page-header :title="$user->name" class="gc-profile-hero__heading {{ $hasLongName ? 'gc-profile-hero__heading--long' : '' }}" :embedded="true">
                    <x-slot:portrait><x-gc-avatar :user="$user" size="xxl" class="flex-shrink-0" alt="" /></x-slot:portrait>
                    <x-slot:breadcrumb><a href="{{ \App\Support\CommunityNavigation::returnUrl(request(), $user->id) }}">← Сообщество</a></x-slot:breadcrumb>
                    <x-slot:summary>
                        <div class="gc-actions mt-2">
                            <button type="button" class="gc-text-action gc-rank-label" data-bs-toggle="popover" data-bs-trigger="focus" title="Ранги" data-bs-html="true" data-bs-content="{{ \App\Rank::getRanksListHTML($rank) }}" aria-label="Ранг {{ $rank->name }}. Шкала рангов"><i class="far fa-star" aria-hidden="true"></i>{{ $rank->name }}</button>
                            @include('profile.partials.custom_title_badge', ['profileUser' => $user, 'compact' => false])
                            @if($user->is_teacher || in_array($user->role, ['teacher', 'admin']))<span class="gc-help-text">Преподаватель</span>@endif
                            @if($user->is_trainee)<span class="gc-help-text">Стажёр</span>@endif
                        </div>
                        <div class="gc-rank-progress mt-3" aria-label="Прогресс к следующему рангу">
                            <div class="gc-summary-line mb-2"><span class="gc-amount">{{ $score }} XP</span><span class="gc-help-text">До следующего ранга {{ max(0, $rank->to - $score) }} XP</span></div>
                            <x-gc-progress :percent="$progressPercent" height="6px" />
                        </div>
                    </x-slot:summary>
                    @if($isOwner || $guest->role == 'admin')
                        <a href="{{ url('/insider/profile/'.$user->id.'/edit') }}" class="btn btn-outline-secondary btn-sm">{{ $isOwner ? 'Личные настройки' : 'Редактировать профиль' }}</a>
                    @endif
                    @if($canManageMoney)
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addMoney">Операция с GC</button>
                    @endif
                    @if($canViewMoneyHistory)
                        @if($isOwner)
                            <a class="gc-balance-pill text-decoration-none" href="{{ url('/insider/market#market-history') }}" aria-label="Баланс {{ $coinBalance }} GC, история операций"><i class="fas fa-coins" aria-hidden="true"></i><strong>{{ $coinBalance }}</strong> GC</a>
                        @else
                            <button class="gc-balance-pill" type="button" data-bs-toggle="modal" data-bs-target="#gc-history" aria-label="Баланс {{ $coinBalance }} GC, история операций"><i class="fas fa-coins" aria-hidden="true"></i><strong>{{ $coinBalance }}</strong> GC</button>
                        @endif
                    @endif
                </x-gc-page-header>
                <div class="gc-profile-hero__details">
                    @include('profile.partials.contacts', ['embedded' => true])
                    @if($hasAbout) @include('profile.partials.about', ['embedded' => true]) @endif
                </div>
            </div>
            <section id="learning-avatar" class="gc-profile-hero__room" aria-label="Комната программиста">
                @php($roomBackdrop = collect($learningAvatarData['layers'])->firstWhere('equippedSlot', 'room'))
                @if($roomBackdrop)<span class="gc-profile-room-backdrop" style="background-image:url('{{ $roomBackdrop['src'] }}')" aria-hidden="true"></span>@endif
                @if($isOwner)
                    <div class="gc-profile-hero__room-controls">
                        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="offcanvas" data-bs-target="#room-settings" aria-controls="room-settings">Настроить</button>
                        @if(($roomCharacterGeneration ?? null)?->isActive())<span class="gc-help-text gc-profile-hero__room-state" role="status">Создаём персонажа…</span>@endif
                    </div>
                @endif
                <x-gc-learning-avatar :user="$user" :data="$learningAvatarData" id="learning-avatar-preview-{{ $user->id }}" data-learning-avatar-preview data-custom-character-url="{{ $user->roomCharacterImageUrl() }}" />

            </section>
        </section>
        <div class="gc-profile-resume {{ $achievements->isEmpty() ? 'gc-profile-resume--single' : '' }}">
            @include('profile.partials.courses')
            @if($achievements->isNotEmpty())
                @include('profile.partials.achievements')
            @endif
        </div>
        @if($hasTeacherComment)
            <x-gc-panel title="Комментарий преподавателя" id="profile-teacher-comment">
                <p class="gc-preserve-lines mb-0">{{ $user->comments }}</p>
            </x-gc-panel>
        @endif
        @if($album->isNotEmpty())
            @include('profile.partials.stickers')
        @endif
    </div>
    @if($isOwner)
        @include('profile.partials.room-settings')
        <span data-profile-history-url="{{ url('/insider/market#market-history') }}" hidden></span>
    @elseif($canViewMoneyHistory)
        <div class="modal fade" id="gc-history" tabindex="-1" aria-labelledby="gc-history-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header"><h2 class="modal-title h5" id="gc-history-title">История GC · {{ $user->name }}</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
                <div class="modal-body"><p class="text-muted">Последние 20 операций · баланс {{ $coinBalance }} GC</p><x-gc-wallet-history :transactions="$coinTransactions" /></div>
            </div></div>
        </div>
    @endif
    @include('profile.partials.management-dialogs')
@endsection
