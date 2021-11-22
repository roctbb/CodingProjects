@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$project->name}}"
@endsection

@section('tabs')

@endsection


@section('content')

    @if(Session::has('message'))
        <div class="alert alert-success alert-dismissible role=" alert">
        {{ Session::get('message') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span
                    aria-hidden="true">&times;</span>
        </button>
        </div>
    @endif

    <div class="row">
        {{ csrf_field() }}

        <div class="col-md-12">
            <div class="card">

                <div class="card-body">
                    <div class="row">
                        <div class="col col-md-auto" style="display:flex;">
                        <h3 class="card-title">{{$project->name}}</h3>
                        @if ($project->getRewardAmount() > 0)
                            <div class="d-flex" style="margin: 10px;">
                                <img src="{{ url('images/icons/icons8-coins-48.png') }}" width="20" height="20"
                                    alt="Rewarded: ">
                                <p>{{$project->getRewardAmount()}}</p></div>
                        @endif
                        @if ($project->basedOn->count() > 0)
                            <small style="margin:10px; display:inline-block;">основано на идее {{$project->basedOn[0]->idea->name}}</small>
                        @endif
                        </div>
                        <div class="col">
                            @if ($user->role=='teacher' || $user->role=='admin' || $is_in_project)
                                <div class="float-right">

                                        @if ($project->author_ !== null)
                                    </p>@if (\Auth::user()->id !== $project->author_->id || \Auth::user()->role == "admin")
                                            <a href="/insider/projects/{{$project->id}}/reward" class="btn btn-warning btn-sm"><i class="icon ion-trophy"></i></a>
                                        @endif
                                        @endif
                                    @if ($user->role != 'student' && $user->role != 'novice' && $project->task != null)
                                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal"
                                                data-target="#reviewModal">
                                            Оценить решение
                                        </button>
                                    @endif
                                    @if ($user->role == 'student' && $project->task != null)
                                        <a href="{{url('/insider/projects/'.$project->id.'/ask_review')}}"
                                           class="btn btn-primary btn-sm">Попросить оценить</a>
                                    @endif
                                    <a href="{{url('/insider/projects/'.$project->id.'/edit')}}"
                                       class="btn btn-success btn-sm"><i class="icon ion-android-create"></i></a>
                                    @if ($user->role=='teacher' || $user->role=='admin' ||($project->author == null || $user->id == $project->author->id))
                                        <a href="{{url('/insider/projects/'.$project->id.'/delete')}}"
                                           class="btn btn-danger btn-sm" onclick="return confirm('Вы уверены?')"><i
                                                    class="ion-close-round"></i></a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    <p>
                        <strong class="lead">{{$project->short_description}}</strong>
                    </p>
                    <div class="row">
                        <div class="col-12">
                            <div class="card-deck">
                                @foreach($project->team as $member)
                                    <div class="card">
                                        <div class="card-body">
                                            @if ($member->image)
                                                <img src="{{url('/media/'.$member->image)}}"
                                                     style="width: 50px; margin: 0;margin-right: 10px;"
                                                     class="img-thumbnail" align="left">
                                            @else

                                                <img src="https://api.adorable.io/avatars/250/{{$member->id}}.png"
                                                     style="width: 50px; margin: 0;margin-right: 10px;"
                                                     class="img-thumbnail" align="left">
                                            @endif
                                            <strong><a href="{{url('/insider/profile/'.$member->id)}}" target="_blank"
                                                       style="color: black">{{$member->name}}</a></strong><br>
                                            <span class="badge badge-pill badge-success"><i
                                                        class="icon ion-ios-arrow-up"></i> {{$member->rank()->name}}</span>

                                            @if ($member->is_trainee)
                                                <span class="badge badge-pill badge-info">Стажер</span>
                                            @endif
                                            @if ($member->is_teacher)
                                                <span class="badge badge-pill badge-info">Преподаватель</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                                @if ($project->task != null)
                                    <div class="card">
                                        <div class="card-body">
                                            <strong>Учебный проект</strong>
                                            <p>Этот проект - решение задачи <u>{{$project->task->name}}</u> в курсе
                                                "{{$project->task_course->name}}".</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if ($project->description != "")
                        @parsedown($project->description)</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($user->role != 'student' and $project->task!=null)
        <div class="modal fade" id="reviewModal" tabindex="-1" role="dialog" aria-labelledby="reviewModalLabel"
             aria-hidden="true">
            <form class="form-horizontal" method="post"
                  action="{{url('insider/projects/'.$project->id.'/review')}}">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="reviewModalLabel">{{$project->task->name}} <span
                                        class="small text-muted">(Курс {{$project->task_course->name}})</span></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body markdown">
                            @parsedown($project->task->text)

                            <span class="badge badge-secondary">очков опыта: {{$project->task->max_mark}}</span>


                            {{csrf_field()}}
                            <div class="form-group">
                                <input type="text" class="form-control form-control-sm mb-2 mr-sm-2 mb-sm-0"
                                       id="mark"
                                       name="mark" placeholder="Очков опыта">
                                @if ($errors->has('mark'))
                                    <span class="help-block error-block"><strong>{{ $errors->first('mark') }}</strong></span>
                                @endif
                            </div>
                            <div class="form-group">
                                        <textarea class="form-control" name="comment"
                                                  placeholder="Комментарий"></textarea>

                            </div>
                            <button type="submit" class="btn btn-success btn-sm">Оценить</button>

                        </div>
                    </div>
                </div>
            </form>
        </div>
    @endif





@endsection
