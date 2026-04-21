@extends('layouts.left-menu')

@section('title')
    {{ $user->name }}
@endsection

@section('content')
    <div class="profile-details-view">
    <div class="row align-items-center g-2">
        <div class="col-12 col-xl">
            <h2 class="profile-details-title">{{$user->name}}</h2>
        </div>
        <div class="col-12 col-xl-auto">
            <div class="profile-details-meta">
                @if ($user->is_trainee)
                    <span class="badge rounded-pill text-bg-info">Стажер</span>
                @endif
                @if ($user->is_teacher)
                    <span class="badge rounded-pill text-bg-info">Преподаватель</span>
                @endif
                <span class="badge rounded-pill text-bg-light">Баланс: {{$user->balance()}}</span>
            </div>
        </div>
    </div>


    <div class="row profile-details-main-row">

        <div class="col-md-8 profile-details-main-col">
            <div class="card profile-about-card">
                <div class="card-body">
                    <h4 class="card-title profile-details-about-title">О себе
                        @if ($guest->role=='admin' || $guest->id==$user->id)
                            <div class="dropdown profile-about-actions">
                                <button class="btn-options" type="button" data-bs-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item"
                                       href="{{url('insider/profile/'.$user->id.'/edit')}}"><i
                                                class="icon fa-solid fa-pen-to-square"></i>
                                        Редактировать</a>
                                    @if ($guest->role=='teacher' || $guest->role=='admin')
                                        <a href="#" class="dropdown-item"
                                           role="button"
                                           data-bs-toggle="modal" data-bs-target="#addMoney">
                                            <i class="icon fa-solid fa-money-bill-wave"></i> Начислить
                                        </a>
                                    @endif
                                </div>
                            </div>

                        @endif
                    </h4>
                    <p><strong>Технологические интересы:</strong><br>{{ $user->interests ?: 'Не указано' }}</p>
                    <p><strong>Увлечения:</strong><br>{{ $user->hobbies ?: 'Не указано' }}</p>
                    @if ($guest->role=='teacher' || $guest->role=='admin')
                        <p><strong>Комментарий:</strong><br>{{ $user->comments ?: 'Не указано' }}</p>
                    @endif
                </div>
            </div>
            @if($user->managed_courses->count()!=0)
                <h4 class="card-title profile-section-title">Преподаватель в курсах <img class="profile-section-icon"
                            src="{{ url('images/icons/icons8-school-director-48.png') }}"></h4>
                <div class="row">
                    @foreach($user->managed_courses as $course)
                        @if ($course->state == 'started')
                            <div class="col-md-6">
                                <div class="card profile-section-card">
                                    <div class="card-body">
                                        <h5 class="card-title">{{$course->name}}</h5>
                                        <p>
                                            <span class="badge rounded-pill text-bg-{{$course->provider->color}}">{{$course->provider->short_name}}</span>
                                        </p>
                                        @if ($guest->role=='admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                            <a href="{{url('insider/courses/'.$course->id)}}" class="card-link">Страница
                                                курса</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
            @if($user->courses()->where('state', 'started')->count()!=0)
                <h4 class="card-title profile-section-title"> Текущие курсы <img class="profile-section-icon"
                            src="{{ url('images/icons/icons8-graduation-cap-48.png') }}"></h4>
                <div class="row">
                    @foreach($user->courses as $course)
                        @if ($course->state == 'started')
                            <div class="col-md-6">
                                <div class="card profile-section-card">
                                    <div class="card-body">
                                        <h5 class="card-title">{{$course->name}}</h5>
                                        <p>
                                            <span class="badge rounded-pill text-bg-{{$course->provider->color}}">{{$course->provider->short_name}}</span>
                                        </p>
                                        @if ($guest->role=='admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                            <a href="{{url('insider/courses/'.$course->id)}}" class="card-link">Страница
                                                курса</a>
                                        @endif
                                        @if ($guest->role=='admin')
                                            <br>
                                            <a href="{{url('insider/profile/'.$user->id.'/delete-course/'.$course->id)}}"
                                               class="card-link profile-danger-link"
                                               onclick="return confirm('Вы уверены?')">Отчислить</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif

            @if($user->completedCourses->count()!=0 || $guest->role=='admin')
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="card-title profile-section-title">Завершенные курсы <img class="profile-section-icon"
                                    src="{{ url('images/icons/icons8-gold-medal-48.png') }}"></h4>
                    </div>
                    <div class="col profile-section-action-col">
                        @if ($guest->role=='admin')
                            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#exampleModal">
                                <i class="fa-solid fa-plus"></i>
                            </button>

                        @endif
                    </div>
                </div>
                <div class="row">
                    @foreach($user->completedCourses as $course)
                        <div class="col-md-6">
                            <div class="card profile-section-card">
                                <div class="card-body">
                                    <h5 class="card-title d-flex justify-content-between align-items-start gap-2">{{$course->name}}
                                        @if ($guest->role=='admin')
                                            <a class="text-danger text-decoration-none profile-course-remove-link"
                                               href="{{url('/insider/profile/delete-course/'.$course->id)}}"
                                               onclick="return confirm('Вы уверены?')">
                                                <i class="icon fa-solid fa-xmark"></i>
                                            </a>
                                        @endif</h5>
                                    <p>
                                        <span class="badge rounded-pill text-bg-{{\App\CourseLabel::get($course)}}">{{$course->provider}}</span>
                                        <span class="badge rounded-pill text-bg-success">Очков опыта: <strong>{{$course->mark}}</strong></span>
                                    </p>
                                    @if ($course->course_id!=null && ($guest->role=='teacher' || $course->course->students->contains($guest)))
                                        <a href="{{url('insider/courses/'.$course->course_id)}}"
                                           class="card-link">Страница
                                            курса</a>
                                    @endif


                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if ($user->orders->count()!=0)
                <div class="row">
                    <div class="col-md-8">
                        <h4 class="card-title profile-section-title">Покупки <img class="profile-section-icon"
                                    src="{{ url('images/icons/icons8-shopping-basket-48.png') }}"></h4>
                    </div>
                </div>

                <div class="row">
                    @foreach($user->orders as $deal)
                        <div class="col-md-6">
                            <div class="card profile-section-card">
                                <div class="card-body">
                                    <h5 class="card-title">{{$deal->good->name}}</h5>
                                    <p>
                                        @if ($deal->shipped)
                                            <span class="badge text-bg-success">
                    Доставлено
                </span>
                                        @else
                                            <span class="badge text-bg-warning">
                    Доставляется...
                </span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-md-4 profile-details-side-col">

            <div class="card profile-side-card">
                @if ($user->image)
                    <img class="card-img-top"
                         src="{{url('/media/'.$user->image)}}" alt="{{ $user->name }}"/>
                @else
                    <img class="card-img-top"
                         src="{{ url('images/user.jpg') }}" alt="{{ $user->name }}"/>
                @endif
                <div class="card-body">

                    <p><strong>Дата
                            рождения:</strong> @if($user->birthday!=null){{$user->birthday->format('Y-m-d')}}@endif<br>
                        <strong>Место учебы:</strong> {{$user->school}}<br>
                        <strong>Класс:</strong> {{$user->grade()}}</p>

                    @php
                        $rankFrom = $user->rank()->from;
                        $rankTo = $user->rank()->to;
                        $rankScore = $user->score();
                        $rankRange = max(1, $rankTo - $rankFrom);
                        $rankProgress = max(0, min(100, (100 * ($rankScore - $rankFrom)) / $rankRange));
                    @endphp
                    <div class="progress">
                        <div class="progress-bar" role="progressbar"
                             style="width:{{ $rankProgress }}%;"
                             aria-valuenow="{{ $rankScore }}" aria-valuemin="{{ $rankFrom }}"
                             aria-valuemax="{{ $rankTo }}">{{ $rankScore }}</div>
                    </div>
                    <p class="profile-summary">
                        <a tabindex="0" data-bs-toggle="popover" data-bs-trigger="focus" title="Ранги" data-bs-html="true"
                           data-bs-content="{{\App\Rank::getRanksListHTML($user->rank())}}"><span class="badge rounded-pill text-bg-success profile-rank-badge"><i
                                        class="icon fa-solid fa-arrow-up"></i> {{$user->rank()->name}}</span></a>
                        <br>
                        @if ($user->is_trainee)
                            <span class="badge rounded-pill text-bg-info">Стажер</span>
                        @endif
                        @if ($user->is_teacher)
                            <span class="badge rounded-pill text-bg-info">Преподаватель</span>
                        @endif
                        <img src="{{ url('images/icons/icons8-coins-48.png') }}" alt="coins"
                             class="profile-coins-icon">&nbsp;{{$user->balance()}}&nbsp;&nbsp;
                    </p>
                    <ul class="list-group list-group-flush profile-contact-list">
                        @if ($user->telegram!='')
                            <li class="list-group-item"><img src="{{ url('images/icons/icons8-telegram-app-48.png') }}"
                                                             alt="Telegram" title="Telegram App" width="16"
                                                             height="16"><strong> Telegram: </strong>
                                {{$user->telegram}}</li>
                        @endif
                        @if ($user->git!='')
                            <li class="list-group-item"><img src="{{ url('images/icons/icons8-git-48.png') }}" alt="Git" title="Git"
                                                             width="16" height="16"><strong> Git: </strong>
                                {{$user->git}}</li>
                        @endif
                        <li class="list-group-item"><i class="icon fa-solid fa-envelope"></i><strong> E-Mail: </strong>
                            {{$user->email}}</li>
                    </ul>

                    @foreach($stickers as $sticker)
                        <img src="{{url($sticker)}}" title="{{$sticker_description[$sticker]}}" alt="sticker"
                             class="profile-sticker"/>
                    @endforeach
                </div>


            </div>

        </div>
    </div>

    <div class="modal fade" id="exampleModal" tabindex="-1"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Добавление курса</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{url('/insider/profile/'.$user->id.'/course')}}" method="POST" class="vstack gap-3">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="completed_course_name">Название</label>
                            <input type="text" name="name" class="form-control" id="completed_course_name"/>
                            @if ($errors->has('name'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="completed_course_mark">Очков опыта</label>
                            <input type="number" min="0" step="1" name="mark" class="form-control" id="completed_course_mark"/>
                            @if ($errors->has('mark'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('mark') }}</strong>
                                </span>
                            @endif
                        </div>
                        <div class="mb-3">
                            <label for="completed_course_provider">Организация</label>
                            <input type="text" name="provider" class="form-control" id="completed_course_provider"/>
                            @if ($errors->has('provider'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('provider') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Создать</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addMoney" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Начисление GC</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="{{url('/insider/profile/'.$user->id.'/money')}}" method="POST" class="vstack gap-3">
                        {{ csrf_field() }}
                        <div class="mb-3">
                            <label for="money_description">За что?</label>
                            <input type="text" name="description" class="form-control" id="money_description"/>
                            @if ($errors->has('description'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('description') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label for="money_amount">Сколько?</label>
                            <input type="number" name="amount" class="form-control" id="money_amount"/>
                            @if ($errors->has('amount'))
                                <span class="invalid-feedback d-block">
                                    <strong>{{ $errors->first('amount') }}</strong>
                                </span>
                            @endif
                        </div>


                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">Начислить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.CPUI) {
                window.CPUI.initPopovers('[data-bs-toggle="popover"]');
                window.CPUI.initPopovers('.popover-dismiss', {trigger: 'focus'});
            }
        });
    </script>

@endsection
