@extends('layouts.left-menu')

@section('title', $user->name)

@section('content')
    @php
        $rank = $user->rank();
        $progressPercent = 100 * ($user->score() - $rank->from) / ($rank->to - $rank->from);
        $managedCourses = $user->managed_courses->where('state', 'started');
        $startedCourses = $user->courses->where('state', 'started');
        $completedCourses = $user->completedCourses;
        $orders = $user->orders;
        $hasAbout = $user->interests || $user->hobbies || (($guest->role == 'teacher' || $guest->role == 'admin') && $user->comments);
        $activeCustomTitle = $user->activeCustomTitle();
        $customTitleCost = $user->customTitleCost();
        $canBuyCustomTitle = $guest->id == $user->id && $coinBalance >= $customTitleCost;
        $avatarFrames = $avatarFrames ?? \App\User::avatarFrames();
        $activeAvatarFrame = $activeAvatarFrame ?? $user->activeAvatarFrame();
        $activeAvatarFrameConfig = $user->activeAvatarFrameConfig();
        $telegramBotConfigured = trim((string) config('services.telegram.bot_username')) !== '';
    @endphp

    <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
            <div class="gc-card gc-profile-card overflow-hidden">
                <div class="p-3 p-md-4 text-center border-bottom">
                    <x-gc-avatar :user="$user" size="xl" img-class="profile-avatar" class="mb-3 mx-auto" alt="" />
                    <h2 class="h5 fw-bold lh-sm mb-2">{{ $user->name }}</h2>
                    @if ($activeCustomTitle)
                        <div class="mb-2">
                            @include('profile.partials.custom_title_badge', ['profileUser' => $user])
                        </div>
                    @endif
                    <div class="d-flex flex-wrap justify-content-center gap-1 mb-3">
                        <a tabindex="0" data-bs-toggle="popover" data-bs-trigger="focus" title="Ранги" data-bs-html="true"
                           data-bs-content="{{ \App\Rank::getRanksListHTML($rank) }}" class="text-decoration-none">
                            <span class="gc-soft-badge"><i class="fas fa-arrow-up me-1"></i>{{ $rank->name }}</span>
                        </a>
                        @if ($user->is_trainee)
                            <span class="gc-soft-badge">Стажер</span>
                        @endif
                        @if ($user->is_teacher)
                            <span class="gc-soft-badge">Преподаватель</span>
                        @endif
                    </div>
                    <div class="gc-balance-pill">
                        <img src="{{ url('images/icons/icons8-coins-48.png') }}" width="18" height="18" alt="">
                        <strong class="text-body">{{ $coinBalance }}</strong>
                        <span>GC</span>
                    </div>
                </div>

                <div class="p-3">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="gc-meta-label">Прогресс</span>
                            <span class="text-muted small">{{ $user->score() }} / {{ $rank->to }} XP</span>
                        </div>
                        <x-gc-progress :percent="$progressPercent" height="6px" />
                    </div>

                    <div class="d-flex flex-column gap-2 small border-top pt-3">
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Дата рождения</span>
                            <strong class="text-end fw-semibold">@if($user->birthday){{ $user->birthday->format('d.m.Y') }}@else - @endif</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Учеба</span>
                            <strong class="text-end fw-semibold">{{ $user->school ?: '-' }}</strong>
                        </div>
                        <div class="d-flex justify-content-between gap-3">
                            <span class="text-muted">Класс</span>
                            <strong class="text-end fw-semibold">{{ $user->grade() }}</strong>
                        </div>
                        @if ($guest->id == $user->id || $guest->role == 'teacher' || $guest->role == 'admin')
                            <div class="d-flex justify-content-between gap-3">
                                <span class="text-muted">Почта</span>
                                <a class="text-end fw-semibold text-decoration-none text-break" href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                            </div>
                        @endif
                    </div>

                    @if ($user->telegram || $user->git)
                        <div class="border-top mt-3 pt-3 d-flex flex-column gap-2 small">
                            @if ($user->telegram)
                                <div class="d-flex align-items-center gap-2 min-width-0">
                                    <i class="fab fa-telegram text-primary"></i>
                                    <span class="text-truncate">{{ $user->telegram }}</span>
                                </div>
                            @endif
                            @if ($user->git)
                                <div class="d-flex align-items-center gap-2 min-width-0">
                                    <i class="fab fa-github text-muted"></i>
                                    <span class="text-truncate">{{ $user->git }}</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($stickers->count())
                        <div class="border-top mt-3 pt-3">
                            <div class="gc-eyebrow mb-2">Наклейки</div>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($stickers as $sticker)
                                    <img src="{{ url($sticker) }}" title="{{ $sticker_description[$sticker] ?? '' }}" height="32" alt="" class="gc-sticker">
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            @if ($guest->role == 'admin' || $guest->id == $user->id)
                <div class="gc-card p-2 d-flex gap-2 mt-3 profile-actions-card">
                    <a href="{{ url('insider/profile/'.$user->id.'/edit') }}" class="btn btn-outline-primary rounded-3 btn-sm flex-fill fw-semibold">
                        <i class="fas fa-edit me-1"></i>Редактировать
                    </a>
                    @if ($guest->role == 'teacher' || $guest->role == 'admin')
                        <button type="button" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addMoney">
                            <i class="fas fa-coins me-1"></i>Начислить
                        </button>
                    @endif
                </div>
            @endif

            @if ($guest->id == $user->id)
                <div class="gc-card profile-telegram-card overflow-hidden mt-3">
                    <div class="gc-section-header">
                        <div class="d-flex align-items-center gap-2 min-width-0">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fab fa-telegram"></i></span>
                            <div class="min-width-0">
                                <span class="gc-eyebrow">уведомления</span>
                                <h6 class="mb-0 text-truncate">Telegram</h6>
                            </div>
                        </div>
                    </div>
                    <div class="p-3">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <span class="text-muted small">Статус</span>
                            @if($user->telegram_chat_id)
                                <span class="badge rounded-pill bg-success-subtle text-success-emphasis border border-success-subtle">Подключено</span>
                            @else
                                <span class="badge rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle">Не подключено</span>
                            @endif
                        </div>

                        @if($telegramBotConfigured)
                            <div class="d-grid gap-2">
                                <a class="btn btn-outline-primary rounded-3 fw-semibold" href="{{ url('/insider/profile/'.$user->id.'/telegram-link') }}">
                                    <i class="fab fa-telegram me-1"></i>{{ $user->telegram_chat_id ? 'Переподключить Telegram' : 'Подключить Telegram' }}
                                </a>
                                @if($user->telegram_chat_id)
                                    <button type="submit" form="telegram-unlink-form" class="btn btn-outline-danger rounded-3 fw-semibold" data-confirm="Отключить Telegram-уведомления?">
                                        <i class="fas fa-link-slash me-1"></i>Отключить
                                    </button>
                                @endif
                            </div>
                            <small class="text-muted d-block mt-2">Откроется бот с одноразовой ссылкой. Нажмите Start, и уведомления привяжутся автоматически.</small>
                        @elseif($user->telegram_chat_id)
                            <button type="submit" form="telegram-unlink-form" class="btn btn-outline-danger rounded-3 fw-semibold w-100" data-confirm="Отключить Telegram-уведомления?">
                                <i class="fas fa-link-slash me-1"></i>Отключить
                            </button>
                            <small class="text-muted d-block mt-2">Бот сейчас не настроен администратором, но уже сохраненную привязку можно отключить.</small>
                        @else
                            <small class="text-muted d-block">Telegram-бот пока не настроен администратором.</small>
                        @endif
                    </div>
                </div>

                <form id="telegram-unlink-form" method="POST" action="{{ url('/insider/profile/'.$user->id.'/telegram-unlink') }}" class="d-none">
                    @csrf
                </form>

                <div class="gc-card profile-custom-title-card overflow-hidden mt-3">
                    <div class="gc-section-header">
                        <div class="d-flex align-items-center gap-2 min-width-0">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-certificate"></i></span>
                            <div class="min-width-0">
                                <span class="gc-eyebrow">{{ $customTitleCost }} GC · 14 дней</span>
                                <h6 class="mb-0 text-truncate">Кастомное звание</h6>
                            </div>
                        </div>
                    </div>
                    <form action="{{ url('/insider/profile/'.$user->id.'/custom-title') }}" method="POST" class="p-3">
                        @csrf
                        <label for="custom-title" class="form-label">Звание</label>
                        <input type="text"
                               name="custom_title"
                               id="custom-title"
                               maxlength="32"
                               class="form-control rounded-3"
                               value="{{ old('custom_title', $activeCustomTitle ?: '') }}"
                               placeholder="Например: Мастер циклов"
                               @if(!$canBuyCustomTitle) disabled @endif>
                        @if ($user->hasActiveCustomTitle())
                            <div class="text-muted small mt-2">
                                Активно до {{ $user->custom_title_expires_at->format('d.m.Y') }}
                            </div>
                        @endif
                        <button type="submit"
                                class="btn btn-sm solution-special-action w-100 justify-content-center mt-3"
                                data-confirm="Купить кастомное звание на 14 дней за {{ $customTitleCost }} GC?"
                                @if(!$canBuyCustomTitle) disabled @endif>
                            <i class="fas fa-magic"></i>
                            Купить за {{ $customTitleCost }} GC
                        </button>
                        @if (!$canBuyCustomTitle)
                            <div class="text-muted small mt-2">Недостаточно GC.</div>
                        @endif
                    </form>
                </div>

                <div class="gc-card profile-avatar-shop-card overflow-hidden mt-3">
                    <div class="gc-section-header">
                        <div class="d-flex align-items-center gap-2 min-width-0">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-user-circle"></i></span>
                            <div class="min-width-0">
                                <span class="gc-eyebrow">от 30 GC · 30 дней</span>
                                <h6 class="mb-0 text-truncate">Рамки аватарки</h6>
                            </div>
                        </div>
                    </div>
                    <div class="profile-avatar-shop-list p-3">
                        @foreach($avatarFrames as $frameKey => $frame)
                            @php
                                $isActiveFrame = $activeAvatarFrame === $frameKey;
                                $canBuyFrame = $coinBalance >= $frame['cost'];
                            @endphp
                            <form action="{{ url('/insider/profile/'.$user->id.'/avatar-frame') }}"
                                  method="POST"
                                  class="profile-avatar-frame-option @if($isActiveFrame) is-active @endif">
                                @csrf
                                <input type="hidden" name="avatar_frame" value="{{ $frameKey }}">
                                <div class="profile-avatar-frame profile-avatar-frame--{{ $frameKey }} profile-avatar-frame--preview flex-shrink-0">
                                    <img src="{{ $user->imageUrl() }}" class="avatar rounded-circle profile-avatar-frame__preview-img" alt="">
                                    <span class="profile-avatar-frame__effect" aria-hidden="true"></span>
                                </div>
                                <div class="min-width-0 flex-grow-1">
                                    <div class="d-flex align-items-center gap-1 fw-semibold text-truncate">
                                        <i class="{{ $frame['icon'] }} text-muted small"></i>
                                        <span class="text-truncate">{{ $frame['name'] }}</span>
                                    </div>
                                    <div class="text-muted small lh-sm">{{ $frame['description'] }}</div>
                                </div>
                                <button type="submit"
                                        class="btn btn-outline-success btn-sm rounded-3 fw-semibold flex-shrink-0"
                                        data-confirm="{{ $isActiveFrame ? 'Продлить' : 'Купить' }} рамку &quot;{{ $frame['name'] }}&quot; на {{ $frame['days'] }} дней за {{ $frame['cost'] }} GC?"
                                        @if(!$canBuyFrame) disabled @endif>
                                    @if($isActiveFrame)
                                        Продлить
                                    @else
                                        {{ $frame['cost'] }} GC
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                    @if($activeAvatarFrameConfig && $user->avatar_frame_expires_at)
                        <div class="border-top px-3 py-2 text-muted small">
                            Активная рамка: {{ $activeAvatarFrameConfig['name'] }} до {{ $user->avatar_frame_expires_at->format('d.m.Y') }}
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="col-lg-8 col-xl-9">
            <div class="row row-cols-2 row-cols-xl-4 g-3 mb-4 profile-summary-grid">
                <div class="col">
                    <div class="gc-card profile-summary-card gc-metric-card">
                        <span class="gc-meta-label">XP</span>
                        <strong>{{ $user->score() }}</strong>
                        <small class="text-muted">до {{ $rank->to }}</small>
                    </div>
                </div>
                <div class="col">
                    <div class="gc-card profile-summary-card gc-metric-card">
                        <span class="gc-meta-label">Курсы</span>
                        <strong>{{ $startedCourses->count() }}</strong>
                        <small class="text-muted">активных</small>
                    </div>
                </div>
                <div class="col">
                    <div class="gc-card profile-summary-card gc-metric-card">
                        <span class="gc-meta-label">Завершено</span>
                        <strong>{{ $completedCourses->count() }}</strong>
                        <small class="text-muted">курсов</small>
                    </div>
                </div>
                <div class="col">
                    <div class="gc-card profile-summary-card gc-metric-card">
                        <span class="gc-meta-label">Покупки</span>
                        <strong>{{ $orders->count() }}</strong>
                        <small class="text-muted">в магазине</small>
                    </div>
                </div>
            </div>

            @if($achievements->count())
                <div class="gc-card overflow-hidden mb-4" id="achievements">
                    <div class="d-flex align-items-center justify-content-between gap-3 gc-section-header">
                        <div class="d-flex align-items-center gap-2 min-width-0">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-trophy"></i></span>
                            <div class="min-width-0">
                                <span class="gc-eyebrow">{{ $achievements->count() }} достижений</span>
                                <h5 class="mb-0 text-truncate">Достижения</h5>
                            </div>
                        </div>
                    </div>
                    <div class="profile-achievement-grid p-3 p-md-4">
                        @foreach($achievements->take(12) as $achievement)
                            <article class="profile-achievement">
                                <span class="profile-achievement__icon"><i class="{{ $achievement->iconClass() }}"></i></span>
                                <div class="profile-achievement__body min-width-0">
                                    <h6 class="profile-achievement__title">{{ $achievement->title }}</h6>
                                    <p class="profile-achievement__description">{{ $achievement->description }}</p>
                                    <div class="profile-achievement__meta">
                                        @if($achievement->task)
                                            <span>{{ $achievement->task->name }}</span>
                                        @endif
                                        @if($achievement->course)
                                            <span>{{ $achievement->course->name }}</span>
                                        @endif
                                        @if($achievement->published_at)
                                            <span>{{ $achievement->published_at->format('d.m.Y') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($canViewMoneyHistory)
                <div class="gc-card overflow-hidden mb-4" id="gc-history">
                    <div class="d-flex align-items-center justify-content-between gap-3 gc-section-header">
                        <div class="d-flex align-items-center gap-2 min-width-0">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-coins"></i></span>
                            <div class="min-width-0">
                                <span class="gc-eyebrow">Баланс {{ $coinBalance }} GC</span>
                                <h5 class="mb-0 text-truncate">История GC</h5>
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm rounded-3 profile-coin-history-toggle"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#gc-history-list"
                                aria-expanded="false"
                                aria-controls="gc-history-list">
                            <i class="fas fa-chevron-down me-1"></i>
                            Показать
                        </button>
                    </div>

                    <div class="collapse" id="gc-history-list">
                        <div class="profile-coin-history">
                            @forelse($coinTransactions as $transaction)
                                @php
                                    $isIncome = $transaction->price > 0;
                                    $formattedAmount = ($isIncome ? '+' : '') . $transaction->price;
                                @endphp
                                <div class="profile-coin-transaction">
                                    <div class="profile-coin-transaction__amount @if($isIncome) is-income @else is-expense @endif">
                                        {{ $formattedAmount }} GC
                                    </div>
                                    <div class="profile-coin-transaction__body min-width-0">
                                        <div class="fw-semibold text-truncate">{{ $transaction->displayComment() }}</div>
                                        <div class="text-muted small">
                                            @if($transaction->created_at)
                                                {{ $transaction->created_at->format('d.m.Y H:i') }}
                                            @else
                                                Дата не указана
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="profile-coin-history__empty">
                                    <span class="gc-icon-tile"><i class="fas fa-coins"></i></span>
                                    <div>
                                        <h6 class="mb-1">Операций пока нет</h6>
                                        <p class="text-muted mb-0 small">Когда появятся начисления, покупки или ставки, они будут видны здесь.</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            <div class="gc-card overflow-hidden mb-4">
                <div class="d-flex align-items-center gap-2 gc-section-header">
                    <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-user"></i></span>
                    <div class="min-width-0">
                        <span class="gc-eyebrow">Профиль</span>
                        <h5 class="mb-0 text-truncate">О себе</h5>
                    </div>
                </div>

                <div class="p-3 p-md-4">
                    @if ($hasAbout)
                        <div class="row g-3">
                            @if ($user->interests)
                                <div class="col-12 col-md-6">
                                    <div class="profile-about-tile h-100">
                                        <div class="profile-about-tile__label">Технологические интересы</div>
                                        <p class="mb-0">{{ $user->interests }}</p>
                                    </div>
                                </div>
                            @endif
                            @if ($user->hobbies)
                                <div class="col-12 col-md-6">
                                    <div class="profile-about-tile h-100">
                                        <div class="profile-about-tile__label">Увлечения</div>
                                        <p class="mb-0">{{ $user->hobbies }}</p>
                                    </div>
                                </div>
                            @endif
                            @if (($guest->role == 'teacher' || $guest->role == 'admin') && $user->comments)
                                <div class="col-12">
                                    <div class="profile-about-tile">
                                        <div class="profile-about-tile__label">Комментарий</div>
                                        <p class="mb-0">{{ $user->comments }}</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @else
                        <p class="text-muted mb-0">Пока без описания.</p>
                    @endif
                </div>
            </div>

            @if($managedCourses->count())
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Преподаёт</h6>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
                    @foreach($managedCourses as $course)
                        <div class="col">
                            <div class="gc-card h-100 p-3 position-relative profile-course-card">
                                <h6 class="fw-bold lh-sm mb-0">{{ $course->name }}</h6>
                                @if ($guest->role == 'admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                    <a href="{{ url('insider/courses/'.$course->id) }}" class="stretched-link" aria-label="Открыть курс {{ $course->name }}"></a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($startedCourses->count())
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Текущие курсы</h6>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
                    @foreach($startedCourses as $course)
                        <div class="col">
                            <div class="gc-card h-100 p-3 profile-course-card">
                                <h6 class="fw-bold lh-sm mb-2">{{ $course->name }}</h6>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    @if ($guest->role == 'admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                        <a href="{{ url('insider/courses/'.$course->id) }}" class="small text-decoration-none">Страница курса</a>
                                    @endif
                                    @if ($guest->role == 'admin')
                                        <a href="{{ url('insider/profile/'.$user->id.'/delete-course/'.$course->id) }}" class="text-danger small text-decoration-none" data-confirm="Вы уверены?">Отчислить</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($completedCourses->count() || $guest->role == 'admin')
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-muted text-uppercase small fw-bold mb-0">Завершённые курсы</h6>
                    @if ($guest->role == 'admin')
                        <button class="btn btn-outline-success rounded-3 btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    @endif
                </div>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
                    @foreach($completedCourses as $course)
                        <div class="col">
                            <div class="gc-card h-100 p-3 profile-course-card">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <h6 class="fw-bold lh-sm mb-0">{{ $course->name }}</h6>
                                    @if ($guest->role == 'admin')
                                        <a href="{{ url('/insider/profile/delete-course/'.$course->id) }}" class="text-danger" data-confirm="Вы уверены?"><i class="fas fa-times"></i></a>
                                    @endif
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="gc-soft-badge">{{ $course->mark }}</span>
                                    @if ($course->course_id && ($guest->role == 'teacher' || $course->course->students->contains($guest)))
                                        <a href="{{ url('insider/courses/'.$course->course_id) }}" class="small text-decoration-none">Страница курса</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($orders->count())
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Покупки</h6>
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3 mb-4">
                    @foreach($orders as $deal)
                        <div class="col">
                            <div class="gc-card h-100 p-3 profile-course-card">
                                <h6 class="fw-bold lh-sm mb-2">{{ $deal->good->name }}</h6>
                                @if ($deal->shipped)
                                    <span class="gc-soft-badge">Доставлено</span>
                                @else
                                    <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">Доставляется...</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Add course modal --}}
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-3 shadow-sm overflow-hidden">
                <div class="modal-header border-bottom p-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-graduation-cap"></i></span>
                        <h5 class="modal-title">Добавление курса</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form action="{{ url('/insider/profile/'.$user->id.'/course') }}" method="POST">
                    @csrf
                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-3">
                            <label for="completed-course-name" class="form-label">Название</label>
                            <input type="text" name="name" class="form-control rounded-3" id="completed-course-name">
                        </div>
                        <div class="mb-3">
                            <label for="completed-course-mark" class="form-label">Очков опыта</label>
                            <input type="number" min="0" name="mark" class="form-control rounded-3" id="completed-course-mark">
                        </div>
                    </div>
                    <div class="modal-footer gc-form-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-success rounded-3">Создать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Add money modal --}}
    <div class="modal fade" id="addMoney" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-3 shadow-sm overflow-hidden">
                <div class="modal-header border-bottom p-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-coins"></i></span>
                        <h5 class="modal-title">Начисление GC</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <form action="{{ url('/insider/profile/'.$user->id.'/money') }}" method="POST">
                    @csrf
                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-3">
                            <label for="money-description" class="form-label">За что?</label>
                            <input type="text" name="description" class="form-control rounded-3" id="money-description">
                        </div>
                        <div>
                            <label for="money-amount" class="form-label">Сколько?</label>
                            <input type="number" name="amount" class="form-control rounded-3" id="money-amount">
                        </div>
                    </div>
                    <div class="modal-footer gc-form-footer">
                        <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-success rounded-3">Начислить</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
