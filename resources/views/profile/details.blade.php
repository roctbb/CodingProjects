@extends('layouts.left-menu')

@section('title', $user->name)

@section('content')
    <div class="row g-4">
        {{-- Sidebar - profile card --}}
        <div class="col-lg-4">
            <div class="card gc-card mb-3">
                <div class="text-center pt-4 pb-2">
                    <img src="{{ $user->imageUrl() }}" class="avatar xl rounded-circle border border-3 border-white shadow-sm" style="width:80px;height:80px;" alt="">
                    <h5 class="mt-2 mb-1">{{ $user->name }}</h5>
                    <div class="mb-2">
                        <a tabindex="0" data-bs-toggle="popover" data-bs-trigger="focus" title="Ранги" data-bs-html="true"
                           data-bs-content="{{ \App\Rank::getRanksListHTML($user->rank()) }}">
                            <span class="badge rounded-pill bg-success"><i class="fas fa-arrow-up me-1"></i>{{ $user->rank()->name }}</span>
                        </a>
                        @if ($user->is_trainee)
                            <span class="badge rounded-pill bg-info">Стажер</span>
                        @endif
                        @if ($user->is_teacher)
                            <span class="badge rounded-pill bg-info">Преподаватель</span>
                        @endif
                    </div>
                    <div class="text-muted small">
                        <img src="{{ url('images/icons/icons8-coins-48.png') }}" height="16" alt="">
                        <strong>{{ $user->balance() }}</strong> GC
                    </div>
                </div>
                <div class="card-body pt-2">
                    <x-gc-progress :percent="100*($user->score()-$user->rank()->from)/($user->rank()->to-$user->rank()->from)" height="6px" />
                    <small class="text-muted d-block mt-1">{{ $user->score() }} / {{ $user->rank()->to }} XP</small>

                    <hr>
                    <div class="small">
                        <p class="mb-1"><strong>Дата рождения:</strong> @if($user->birthday){{ $user->birthday->format('d.m.Y') }}@endif</p>
                        <p class="mb-1"><strong>Учеба:</strong> {{ $user->school }}</p>
                        <p class="mb-1"><strong>Класс:</strong> {{ $user->grade() }}</p>
                    </div>

                    @if ($user->telegram || $user->git)
                        <hr>
                        <div class="small">
                            @if ($user->telegram)
                                <p class="mb-1"><i class="fab fa-telegram me-1"></i><strong>Telegram:</strong> {{ $user->telegram }}</p>
                            @endif
                            @if ($user->git)
                                <p class="mb-1"><i class="fab fa-github me-1"></i><strong>Git:</strong> {{ $user->git }}</p>
                            @endif
                        </div>
                    @endif

                    @if($stickers->count())
                        <hr>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($stickers as $sticker)
                                <img src="{{ url($sticker) }}" title="{{ $sticker_description[$sticker] ?? '' }}" height="32" alt="" class="gc-sticker">
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            @if ($guest->role == 'admin' || $guest->id == $user->id)
                <div class="d-flex gap-2 mb-3">
                    <a href="{{ url('insider/profile/'.$user->id.'/edit') }}" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="fas fa-edit me-1"></i>Редактировать
                    </a>
                    @if ($guest->role == 'teacher' || $guest->role == 'admin')
                        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addMoney">
                            <i class="fas fa-coins me-1"></i>Начислить
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Main content --}}
        <div class="col-lg-8">
            {{-- About --}}
            <div class="card gc-card mb-3">
                <div class="card-body">
                    <h5 class="card-title mb-3">О себе</h5>
                    @if ($user->interests)
                        <p class="mb-2"><strong>Технологические интересы:</strong><br>{{ $user->interests }}</p>
                    @endif
                    @if ($user->hobbies)
                        <p class="mb-2"><strong>Увлечения:</strong><br>{{ $user->hobbies }}</p>
                    @endif
                    @if (($guest->role == 'teacher' || $guest->role == 'admin') && $user->comments)
                        <p class="mb-0"><strong>Комментарий:</strong><br>{{ $user->comments }}</p>
                    @endif
                </div>
            </div>

            {{-- Teaching courses --}}
            @if($user->managed_courses->where('state', 'started')->count())
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Преподаёт</h6>
                <div class="row g-3 mb-4">
                    @foreach($user->managed_courses->where('state', 'started') as $course)
                        <div class="col-sm-6">
                            <div class="card gc-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-1">{{ $course->name }}</h6>
                                    <span class="badge rounded-pill bg-{{ $course->provider->color }}">{{ $course->provider->short_name }}</span>
                                    @if ($guest->role == 'admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                        <a href="{{ url('insider/courses/'.$course->id) }}" class="stretched-link"></a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Current courses --}}
            @if($user->courses()->where('state', 'started')->count())
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Текущие курсы</h6>
                <div class="row g-3 mb-4">
                    @foreach($user->courses->where('state', 'started') as $course)
                        <div class="col-sm-6">
                            <div class="card gc-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-1">{{ $course->name }}</h6>
                                    <span class="badge rounded-pill bg-{{ $course->provider->color }}">{{ $course->provider->short_name }}</span>
                                    @if ($guest->role == 'admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                        <a href="{{ url('insider/courses/'.$course->id) }}" class="d-block small mt-1">Страница курса</a>
                                    @endif
                                    @if ($guest->role == 'admin')
                                        <a href="{{ url('insider/profile/'.$user->id.'/delete-course/'.$course->id) }}" class="text-danger small" data-confirm="Вы уверены?">Отчислить</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Completed courses --}}
            @if($user->completedCourses->count() || $guest->role == 'admin')
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="text-muted text-uppercase small fw-bold mb-0">Завершённые курсы</h6>
                    @if ($guest->role == 'admin')
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal">
                            <i class="fas fa-plus"></i>
                        </button>
                    @endif
                </div>
                <div class="row g-3 mb-4">
                    @foreach($user->completedCourses as $course)
                        <div class="col-sm-6">
                            <div class="card gc-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="card-title mb-1">{{ $course->name }}</h6>
                                        @if ($guest->role == 'admin')
                                            <a href="{{ url('/insider/profile/delete-course/'.$course->id) }}" class="text-danger" data-confirm="Вы уверены?"><i class="fas fa-times"></i></a>
                                        @endif
                                    </div>
                                    <span class="badge rounded-pill bg-{{ \App\CourseLabel::get($course) }}">{{ $course->provider }}</span>
                                    <span class="badge rounded-pill bg-success">{{ $course->mark }}</span>
                                    @if ($course->course_id && ($guest->role == 'teacher' || $course->course->students->contains($guest)))
                                        <a href="{{ url('insider/courses/'.$course->course_id) }}" class="d-block small mt-1">Страница курса</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Purchases --}}
            @if ($user->orders->count())
                <h6 class="text-muted text-uppercase small fw-bold mb-2">Покупки</h6>
                <div class="row g-3 mb-4">
                    @foreach($user->orders as $deal)
                        <div class="col-sm-6">
                            <div class="card gc-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-1">{{ $deal->good->name }}</h6>
                                    @if ($deal->shipped)
                                        <span class="badge bg-success">Доставлено</span>
                                    @else
                                        <span class="badge bg-warning text-dark">Доставляется...</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Add course modal --}}
    <div class="modal fade" id="exampleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Добавление курса</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ url('/insider/profile/'.$user->id.'/course') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label">Название</label>
                            <input type="text" name="name" class="form-control" id="name">
                        </div>
                        <div class="mb-3">
                            <label for="mark" class="form-label">Очков опыта</label>
                            <input type="text" name="mark" class="form-control" id="mark">
                        </div>
                        <div class="mb-3">
                            <label for="provider" class="form-label">Организация</label>
                            <input type="text" name="provider" class="form-control" id="provider">
                        </div>
                        <button type="submit" class="btn btn-success">Создать</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Add money modal --}}
    <div class="modal fade" id="addMoney" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Начисление GC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body">
                    <form action="{{ url('/insider/profile/'.$user->id.'/money') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="description" class="form-label">За что?</label>
                            <input type="text" name="description" class="form-control" id="description">
                        </div>
                        <div class="mb-3">
                            <label for="amount" class="form-label">Сколько?</label>
                            <input type="number" name="amount" class="form-control" id="amount">
                        </div>
                        <button type="submit" class="btn btn-success">Начислить</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
