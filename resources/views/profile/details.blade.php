@extends('layouts.left-menu')

@section('title')
    {{ $user->name }}
@endsection

@section('content')
    <div class="row">
        <div class="col">
            <h2 class="font-weight-light">{{$user->name}}</h2>
        </div>
    </div>


    <div class="row mt-3">

        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mw-100">О себе
                        @if ($guest->role=='admin' || $guest->id==$user->id)
                            <div class="dropdown float-right">
                                <button class="btn-options" type="button" data-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item"
                                       href="{{url('insider/profile/'.$user->id.'/edit')}}"><i
                                                class="icon ion-android-create"></i>
                                        Редактировать</a>
                                    @if ($guest->role=='teacher' || $guest->role=='admin')
                                        <button type="button" class="dropdown-item"
                                           data-toggle="modal" data-target="#addMoney">
                                            <i class="icon ion-cash"></i> Начислить
                                        </button>
                                    @endif
                                </div>
                            </div>

                        @endif
                    </h4>
                    <p><strong>Технологические интересы:</strong><br>{{$user->interests}}</p>
                    <p><strong>Увлечения:</strong><br>{{$user->hobbies}}</p>
                    @if ($guest->role=='teacher' || $guest->role=='admin')
                        <p><strong>Комментарий:</strong><br>{{$user->comments}}</p>
                    @endif
                </div>
            </div>
            @if($user->managed_courses->count()!=0)
                <h4 class="card-title m-3">Преподаватель в курсах <img
                            src="{{ url('images/icons/icons8-school-director-48.png') }}" height="30" alt=""></h4>
                <div class="row">
                    @foreach($user->managed_courses as $course)
                        @if ($course->state == 'started')
                            <div class="col-md-6">
                                <div class="card w-100">
                                    <div class="card-body">
                                        <h5 class="card-title">{{$course->name}}</h5>
                                        <p>
                                            <span class="badge badge-pill badge-{{$course->provider->color}}">{{$course->provider->short_name}}</span>
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
                <h4 class="card-title m-3"> Текущие курсы <img
                            src="{{ url('images/icons/icons8-graduation-cap-48.png') }}" height="30" alt=""></h4>
                <div class="row">
                    @foreach($user->courses as $course)
                        @if ($course->state == 'started')
                            <div class="col-md-6">
                                <div class="card w-100">
                                    <div class="card-body">
                                        <h5 class="card-title">{{$course->name}}</h5>
                                        <p>
                                            <span class="badge badge-pill badge-{{$course->provider->color}}">{{$course->provider->short_name}}</span>
                                        </p>
                                        @if ($guest->role=='admin' || $course->students->contains($guest) || $course->teachers->contains($guest))
                                            <a href="{{url('insider/courses/'.$course->id)}}" class="card-link">Страница
                                                курса</a>
                                        @endif
                                        @if ($guest->role=='admin')
                                            <br>
                                            <a href="{{url('insider/profile/'.$user->id.'/delete-course/'.$course->id)}}"
                                               class="card-link text-danger"
                                               data-confirm="Вы уверены?">Отчислить</a>
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
                         <h4 class="card-title m-3">Завершенные курсы <img
                                    src="{{ url('images/icons/icons8-gold-medal-48.png') }}" height="30" alt=""></h4>
                    </div>
                    <div class="col pt-3">
                        @if ($guest->role=='admin')
                            <button class="btn btn-round float-right" data-toggle="modal" data-target="#exampleModal">
                                <i class="fas fa-plus"></i>
                            </button>

                        @endif
                    </div>
                </div>
                <div class="row">
                    @foreach($user->completedCourses as $course)
                        <div class="col-md-6">
                            <div class="card w-100 mb-2">
                                <div class="card-body">
                                    <h5 class="card-title">{{$course->name}}
                                        @if ($guest->role=='admin')
                                            <a class="float-right"
                                               href="{{url('/insider/profile/delete-course/'.$course->id)}}"
                                               data-confirm="Вы уверены?"><span
                                                        aria-hidden="true">&times;</span></a>
                                        @endif</h5>
                                    <p>
                                        <span class="badge badge-pill badge-{{\App\CourseLabel::get($course)}}">{{$course->provider}}</span>
                                        <span class="badge badge-pill badge-success">Очков опыта: <strong>{{$course->mark}}</strong></span>
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
                         <h4 class="card-title m-3">Покупки <img
                                    src="{{ url('images/icons/icons8-shopping-basket-48.png') }}" height="30" alt=""></h4>
                    </div>
                </div>

                <div class="row">
                    @foreach($user->orders as $deal)
                        <div class="col-md-6">
                            <div class="card w-100 mb-2">
                                <div class="card-body">
                                    <h5 class="card-title">{{$deal->good->name}}</h5>
                                    <p>
                                        @if ($deal->shipped)
                                            <span class="badge badge-success">
                    Доставлено
                </span>
                                        @else
                                            <span class="badge badge-warning">
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
        <div class="col-md-4">

            <div class="card">
                <img class="card-img-top" src="{{ $user->imageUrl() }}"/>
                <div class="card-body">

                    <p><strong>Дата
                            рождения:</strong> @if($user->birthday!=null){{$user->birthday->format('Y-m-d')}}@endif<br>
                        <strong>Место учебы:</strong> {{$user->school}}<br>
                        <strong>Класс:</strong> {{$user->grade()}}</p>

                    <div class="progress">
                        <div class="progress-bar" role="progressbar"
                             data-progress-width="{{100*($user->score()-$user->rank()->from)/($user->rank()->to-$user->rank()->from)}}%"
                             aria-valuenow="{{$user->score()}}" aria-valuemin="{{$user->rank()->from}}"
                             aria-valuemax="{{$user->rank()->to}}">{{$user->score()}}</div>
                    </div>
                     <p class="mt-3">
                        <a tabindex="0" data-toggle="popover" data-trigger="focus" title="Ранги" data-html="true"
                           data-content="{{\App\Rank::getRanksListHTML($user->rank())}}"><span
                                                                                                class="badge badge-pill badge-success small"><i
                                        class="icon ion-ios-arrow-up"></i> {{$user->rank()->name}}</span></a>
                        <br>
                        @if ($user->is_trainee)
                            <span class="badge badge-pill badge-info">Стажер</span>
                        @endif
                        @if ($user->is_teacher)
                            <span class="badge badge-pill badge-info">Преподаватель</span>
                        @endif
                        <img src="{{ url('images/icons/icons8-coins-48.png') }}"
                              height="23" alt="">&nbsp;{{$user->balance()}}&nbsp;&nbsp;
                    </p>
                    <ul class="list-group list-group-flush shadow-none">
                        @if ($user->telegram!='')
                            <li class="list-group-item"><img src="{{ url('images/icons/icons8-telegram-app-48.png') }}"
                                                             title="Telegram App" width="16"
                                                             height="16"><strong> Telegram: </strong>
                                {{$user->telegram}}</li>
                        @endif
                        @if ($user->git!='')
                            <li class="list-group-item"><img src="{{ url('images/icons/icons8-git-48.png') }}" title="Git"
                                                             width="16" height="16"><strong> Git: </strong>
                                {{$user->git}}</li>
                        @endif
                    </ul>

                    @foreach($stickers as $sticker)
                        <img src="{{url($sticker)}}" title="{{$sticker_description[$sticker]}}"
                              height="35" alt=""/>
                    @endforeach
                </div>


            </div>

        </div>
    </div>

    <div class="modal fade" id="exampleModal" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Добавление курса</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('/insider/profile/'.$user->id.'/course')}}" method="POST"
                         >
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="name" class="col-md-4">Название</label>

                            <div class="col-md-12">
                                <input type="text" name="name" class="form-control" id="name"/>
                                @if ($errors->has('name'))
                                    <span class="text-danger d-block">
                <strong>{{ $errors->first('name') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="mark" class="col-md-4">Очков опыта</label>

                            <div class="col-md-12">
                                <input type="text" name="mark" class="form-control" id="mark"/>
                                @if ($errors->has('mark'))
                                    <span class="text-danger d-block">
                <strong>{{ $errors->first('mark') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="provider" class="col-md-4">Организация</label>

                            <div class="col-md-12">
                                <input type="text" name="provider" class="form-control" id="provider"/>
                                @if ($errors->has('provider'))
                                    <span class="text-danger d-block">
                <strong>{{ $errors->first('provider') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success">Создать</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="addMoney" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Начисление GC</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('/insider/profile/'.$user->id.'/money')}}" method="POST"
                         >
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="description" class="col-md-4">За что?</label>

                            <div class="col-md-12">
                                <input type="text" name="description" class="form-control" id="description"/>
                                @if ($errors->has('description'))
                                    <span class="text-danger d-block">
                <strong>{{ $errors->first('description') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="amount" class="col-md-4">Сколько?</label>

                            <div class="col-md-12">
                                <input type="number" name="amount" class="form-control" id="amount"/>
                                @if ($errors->has('amount'))
                                    <span class="text-danger d-block">
                <strong>{{ $errors->first('amount') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>


                        <div class="form-group">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success">Начислить</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
