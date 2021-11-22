@extends('layouts.left-menu', ["disabletheme" => true])
@section('head')
    @if($user->currentTheme())
        <link rel="stylesheet" href="/insider/themes/{{$user->currentTheme()->id}}/css"></style>
        <script src="/insider/themes/{{$user->currentTheme()->id}}/js"></script>
    @endif
@endsection
@section('content')
    <div class="row">
        <div class="col">
            <h2 style="font-weight: 300;">{{$user->name}}</h2>
        </div>
    </div>


    <div class="row" style="margin-top: 15px;">

        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title" style="max-width: 100% !important;">О себе
                        @if ($guest->role=='admin' || $guest->id==$user->id)
                            <div class="dropdown float-right">
                                <button class="btn-options" type="button" data-toggle="dropdown"
                                        aria-haspopup="true" aria-expanded="false">
                                    <i class="material-icons">more_vert</i>
                                </button>

                                <div class="dropdown-menu dropdown-menu-right">
                                    <a class="dropdown-item"
                                       href="{{url('insider/profile/'.$user->id.'/edit')}}"><i
                                                class="icon ion-android-create"></i>
                                        Редактировать</a>
                                    @if ($guest->role=='teacher' || $guest->role=='admin')
                                        <a href="#" class="dropdown-item"
                                           role="button"
                                           data-toggle="modal" data-target="#addMoney">
                                            <i class="icon ion-cash"></i> Начислить
                                        </a>
                                    @endif
                                    <a target="_blank" class="dropdown-item"
                                       href="{{'/insider/core/'.$user->id}}">
                                        Core</a>

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
                <h4 style="margin: 20px;" class="card-title">Преподаватель в курсах <img style="height: 30px;"
                            src="{{ url('images/icons/icons8-school-director-48.png') }}"></h4>
                <div class="row">
                    @foreach($user->managed_courses as $course)
                        @if ($course->state == 'started')
                            <div class="col-md-6">
                                <div class="card" style="width: 100%;">
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
                <h4 style="margin: 20px;" class="card-title"> Текущие курсы <img style="height: 30px;"
                            src="{{ url('images/icons/icons8-graduation-cap-48.png') }}"></h4>
                <div class="row">
                    @foreach($user->courses as $course)
                        @if ($course->state == 'started')
                            <div class="col-md-6">
                                <div class="card" style="width: 100%;">
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
                                               style="color: red;" class="card-link"
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
                        <h4 style="margin: 20px;" class="card-title">Завершенные курсы <img style="height: 30px;"
                                    src="{{ url('images/icons/icons8-gold-medal-48.png') }}"></h4>
                    </div>
                    <div class="col" style="padding-top: 19px;">
                        @if ($guest->role=='admin')
                            <button class="btn btn-round float-right" data-toggle="modal" data-target="#exampleModal">
                                <i class="material-icons">add</i>
                            </button>

                        @endif
                    </div>
                </div>
                <div class="row">
                    @foreach($user->completedCourses as $course)
                        <div class="col-md-6">
                            <div class="card" style="width: 100%; margin-bottom: 10px;">
                                <div class="card-body">
                                    <h5 class="card-title">{{$course->name}}
                                        @if ($guest->role=='admin')
                                            <a class="float-right"
                                               href="{{url('/insider/profile/delete-course/'.$course->id)}}"
                                               onclick="return confirm('Вы уверены?')"><span
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

            @if ($user->projects->count()!=0 || $guest->role=='teacher' || $guest->role=='admin')
                <div class="row">
                    <div class="col-md-8">
                        <h4 style="margin: 20px;" class="card-title">Проекты <img style="height: 30px;"
                                                                                  src="{{ url('images/icons/icons8-microsoft-project-48.png') }}">
                        </h4>
                    </div>
                    <div class="col" style="padding-top: 19px;">
                        @if ($guest->role=='admin' || $guest->role=='teacher' || $guest->id==$user->id)
                            <button class="btn btn-round float-right" data-toggle="modal" data-target="#createProject">
                                <i class="material-icons">add</i>
                            </button>
                        @endif
                    </div>

                </div>

                <div class="row">
                    @foreach($user->projects as $project)
                        <div class="col-md-6">
                            <div class="card" style="width: 100%; margin-bottom: 10px;">
                                <div class="card-body">
                                    <h5 class="card-title">{{$project->name}}</h5>
                                    <p><span>{{$project->short_description}}</span></p>
                                    <a href="{{url('insider/projects/'.$project->id)}}" class="card-link">Страница
                                        проекта</a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- @foreach($events as $event)
                @if($event->participants->contains($user->id) && (\Carbon\Carbon::createFromFormat('Y-m-d', $event->date)->lt(\Carbon\Carbon::now())))
                    <div class="row">
                        <div class="col-md-8">
                            <h4 style="margin: 20px;" class="card-title">Прошедшие события
                                <img src="https://png.icons8.com/ultraviolet/50/000000/overtime.png" width="25px">
                            </h4>
                        </div>
                    </div>
                    @break
                @endif
            @endforeach --}}

            <div class="row">
                {{-- @foreach($events as $event)
                    @if($event->participants->contains($user->id) && (\Carbon\Carbon::createFromFormat('Y-m-d', $event->date)->lt(\Carbon\Carbon::now())))
                        <div class="col-md-6">
                            <div class="card" style="width: 100%; margin-bottom: 10px;">
                                <div class="card-footer">
                                    <div class="float-left">
                                        <h5 class="card-title">{{$event->name}}</h5>
                                    </div>
                                    <div class="float-right" style="margin-right:5px">
                                        {{$event->date}}
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="float-left"><span>{{$event->short_text}}</span></div>
                                    <div class="float-right">
                                        <a href="{{url('insider/events/'.$event->id)}}" class="card-link">Страница
                                            события</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach --}}
            </div>

            {{-- @foreach($events as $event)
                @if($event->participants->contains($user->id) && (\Carbon\Carbon::createFromFormat('Y-m-d', $event->date)->gt(\Carbon\Carbon::now())))
                    <div class="row">
                        <div class="col-md-8">
                            <h4 style="margin: 20px;" class="card-title">События <img
                                        src="https://png.icons8.com/ultraviolet/50/000000/today.png" width="25px">
                            </h4>
                        </div>
                    </div>
                    @break
                @endif
            @endforeach --}}

            <div class="row">
                {{-- @foreach($events as $event)
                    @if($event->participants->contains($user->id) && (\Carbon\Carbon::createFromFormat('Y-m-d', $event->date)->gt(\Carbon\Carbon::now())))
                        <div class="col-md-6">
                            <div class="card" style="width: 100%; margin-bottom: 10px;">
                                <div class="card-footer">
                                    <div class="float-left">
                                        <h5 class="card-title">{{$event->name}}</h5>
                                    </div>
                                    <div class="float-right" style="margin-right:5px">
                                        {{$event->date}}
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="float-left"><span>{{$event->short_text}}</span></div>
                                    <div class="float-right">
                                        <a href="{{url('insider/events/'.$event->id)}}" class="card-link">Страница
                                            события</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach --}}
            </div>
            @if ($user->orders->count()!=0)
                <div class="row">
                    <div class="col-md-8">
                        <h4 style="margin: 20px;" class="card-title">Покупки <img style="height: 30px;"
                                    src="{{ url('images/icons/icons8-shopping-basket-48.png') }}"></h4>
                    </div>
                </div>

                <div class="row">
                    @foreach($user->orders as $deal)
                        <div class="col-md-6">
                            <div class="card" style="width: 100%; margin-bottom: 10px;">
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
                @if ($user->image)
                    <img class="card-img-top"
                         src="{{url('/media/'.$user->image)}}"/>
                @else
                    <img class="card-img-top"
                         src="https://api.adorable.io/avatars/250/{{$user->id}}.png"/>
                @endif
                <div class="card-body">

                    <p><strong>Дата
                            рождения:</strong> @if($user->birthday!=null){{$user->birthday->format('Y-m-d')}}@endif<br>
                        <strong>Место учебы:</strong> {{$user->school}}<br>
                        <strong>Класс:</strong> {{$user->grade()}}</p>

                    <div class="progress">
                        <div class="progress-bar" role="progressbar"
                             style="width:{{100*($user->score()-$user->rank()->from)/($user->rank()->to-$user->rank()->from)}}%;"
                             aria-valuenow="{{$user->score()}}" aria-valuemin="{{$user->rank()->from}}"
                             aria-valuemax="{{$user->rank()->to}}">{{$user->score()}}</div>
                    </div>
                    <p style="margin-top: 15px;">
                        <a tabindex="0" data-toggle="popover" data-trigger="focus" title="Ранги" data-html="true"
                           data-content="{{\App\Rank::getRanksListHTML($user->rank())}}"><span style="font-size: 15px;"
                                                                                               class="badge badge-pill badge-success"><i
                                        class="icon ion-ios-arrow-up"></i> {{$user->rank()->name}}</span></a>
                        <br>
                        @if ($user->is_trainee)
                            <span class="badge badge-pill badge-info">Стажер</span>
                        @endif
                        @if ($user->is_teacher)
                            <span class="badge badge-pill badge-info">Преподаватель</span>
                        @endif
                        <img src="{{ url('images/icons/icons8-coins-48.png') }}"
                             style="height: 23px;">&nbsp;{{$user->balance()}}&nbsp;&nbsp;
                    </p>
                    <ul class="list-group list-group-flush" style="box-shadow: none;">
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
                        @if ($user->vk!='')
                            <li class="list-group-item"><img src="{{ url('images/icons/icons8-vk.com-48.png') }}"
                                                             title="VKontakte" width="16"
                                                             height="16">
                                <strong> VK: </strong>
                                {{$user->vk}}</li>
                        @endif
                        @if ($user->facebook!='')
                            <li class="list-group-item"><img src="{{ url('images/icons/icons8-facebook-like-48.png') }}"
                                                             title="Facebook"
                                                             width="16"
                                                             height="16"><strong> Facebook: </strong>
                                {{$user->facebook}}</li>
                        @endif
                    </ul>

                    @foreach($stickers as $sticker)
                        <img src="{{url($sticker)}}" title="{{$sticker_description[$sticker]}}"
                             style="max-height: 35px;"/>
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
                          class="form-horizontal">
                        {{ csrf_field() }}
                        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                            <label for="name" class="col-md-4">Название</label>

                            <div class="col-md-12">
                                <input type="text" name="name" class="form-control" id="name"/>
                                @if ($errors->has('name'))
                                    <span class="help-block error-block">
                <strong>{{ $errors->first('name') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('mark') ? ' has-error' : '' }}">
                            <label for="mark" class="col-md-4">Очков опыта</label>

                            <div class="col-md-12">
                                <input type="text" name="mark" class="form-control" id="mark"/>
                                @if ($errors->has('mark'))
                                    <span class="help-block error-block">
                <strong>{{ $errors->first('mark') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('provider') ? ' has-error' : '' }}">
                            <label for="provider" class="col-md-4">Организация</label>

                            <div class="col-md-12">
                                <input type="text" name="provider" class="form-control" id="provider"/>
                                @if ($errors->has('provider'))
                                    <span class="help-block error-block">
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
                    <h5 class="modal-title">Начисление GT</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('/insider/profile/'.$user->id.'/money')}}" method="POST"
                          class="form-horizontal">
                        {{ csrf_field() }}
                        <div class="form-group{{ $errors->has('description') ? ' has-error' : '' }}">
                            <label for="description" class="col-md-4">За что?</label>

                            <div class="col-md-12">
                                <input type="text" name="description" class="form-control" id="description"/>
                                @if ($errors->has('description'))
                                    <span class="help-block error-block">
                <strong>{{ $errors->first('description') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group{{ $errors->has('amount') ? ' has-error' : '' }}">
                            <label for="amount" class="col-md-4">Сколько?</label>

                            <div class="col-md-12">
                                <input type="number" name="amount" class="form-control" id="amount"/>
                                @if ($errors->has('amount'))
                                    <span class="help-block error-block">
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
    <div class="modal fade" id="createProject" tabindex="-1" role="dialog"
         aria-labelledby="exampleModelLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModelLabel">Добавление проекта</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('/insider/projects/create')}}" method="POST"
                          class="form-horizontal">
                        {{ csrf_field() }}
                        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
                            <label for="name" class="col-md-4">Название</label>

                            <div class="col-md-12">
                                <input type="text" name="name" class="form-control" id="name"/>
                                @if ($errors->has('name'))
                                    <span class="help-block error-block">
                <strong>{{ $errors->first('name') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>
                        <div class="form-group{{ $errors->has('short_description') ? ' has-error' : '' }}">
                            <label for="short_description" class="col-md-4">Краткое описание</label>

                            <div class="col-md-12">
        <textarea name="short_description" class="form-control"
                  id="short_description"></textarea>
                                @if ($errors->has('short_description'))
                                    <span class="help-block error-block">
                <strong>{{ $errors->first('short_description') }}</strong>
            </span>
                                @endif
                            </div>
                        </div>
                    <!--  <div class="form-group{{ $errors->has('projectType') ? ' has-error' : '' }}">
    <label for="short_description" class="col-md-4">Тип</label>
    <div class="col-md-12">
        <select name="projectType" class="form-control" id="projectType"/>
            <option value="None selected">Выберите тип проекта</option>
            <option value="Learning">Учебный</option>
            <option value="Working">Рабочий</option>
        </select>
        @if ($errors->has('short_description'))
                        <span class="help-block error-block">
                            <strong>{{ $errors->first('short_description') }}</strong>
            </span>
        @endif
                            </div>
                        </div>-->


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
    <script>
        $(function () {
            $('[data-toggle="popover"]').popover()
        });
        $('.popover-dismiss').popover({
            trigger: 'focus'
        });
    </script>

@endsection
