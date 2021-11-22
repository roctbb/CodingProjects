@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$course->name}}"
@endsection

@section('tabs')

@endsection



@section('content')
    <link rel="stylesheet" href="{{url('css/alchemy-white.css')}}"/>
    <style>
        .alchemy text {
            display: block !important;
        }

        .alchemy g.active {
            opacity: 1;
        }

        g.use > circle {
            opacity: 0.8 !important;
            stroke: green !important;
            stroke-opacity: 0.8;
            fill: green !important;
        }

        g.target > circle {
            opacity: 0.8 !important;
            stroke: orange !important;
            stroke-opacity: 0.8;
            fill: orange !important;
        }

        g.exists > circle {
            opacity: 0.8;

        }

        .alchemy > svg {
            background: white !important;
        }

        g.exists > text {
            fill: black;
            text-shadow: none;
        }

        g.use > text {
            fill: black !important;
            text-shadow: none;
        }

        g.target > text {
            fill: black !important;
            text-shadow: none;
        }
    </style>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2 style="font-weight: 300;">{{$course->name}}</h2>
            <p>{{$course->description}}</p>
        </div>
        <div class="col">
            <ul class="nav nav-tabs nav-fill" id="lessonsTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="lessons-tab" data-toggle="tab" href="#lessons" role="tab"
                       aria-controls="lessons" aria-selected="true">Доступные уроки</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="done_lessons-tab" data-toggle="tab" href="#done_lessons" role="tab"
                       aria-controls="done_lessons" aria-selected="false">Завершенные уроки</a>
                </li>
            </ul>
        </div>

    </div>

    <div class="tab-content" style="margin-top: 15px;">
        <div class="tab-pane fade show active" id="lessons" role="tabpanel" aria-labelledby="lessons">
            <div class="row">
                <div class="col-md-12">
                    @if ($idea == null)
                        <div class="jumbotron">
                            <h1 class="display-4">Выберите проект</h1>
                            <p class="lead" style="margin-top: 15px;">Прежде чем начать обучение, нам нужно определится
                                с целью, к которой мы стремимся. Вы можете выбрать одну из идей в списке ниже и мы
                                автоматически составим курс из нужных вам материалов.</p>
                            <p class="lead" style="margin-top: 15px;">Собственную идею можно предложить в разделе <a
                                        href="{{url('insider/ideas')}}"
                                        target="_blank">"Идеи"</a>.</p>

                            <form method="post" action="{{url('/insider/courses/'.$course->id.'/sdl_idea')}}">
                                {{ csrf_field() }}

                                <div class="form-group">
                                    <select class="selectpicker form-control" data-live-search="true" id="idea_id"
                                            name="idea_id" data-width="auto">
                                        @foreach (\App\Idea::where('sdl_node_id', '!=', null)->get() as $idea)
                                            <option data-tokens="{{ $idea->id }}" value="{{ $idea->id }}"
                                                    data-subtext="{{$idea->short_description}}">{{$idea->name}}</option>
                                        @endforeach
                                    </select>
                                    @if ($errors->has('idea_id'))
                                        <span class="help-block error-block">
                                        <strong>{{ $errors->first('idea_id') }}</strong>
                                    </span>
                                    @endif
                                </div>

                                <script>
                                    $('.selectpicker').selectpicker();
                                </script>

                                <input type="submit" class="btn btn-primary btn-lg" style="margin-top: 15px;"
                                       value="Приступить к учебе!"/>


                            </form>

                        </div>
                    @else
                        <div class="card-deck">
                            <div class="card" style="min-width:40% !important;">
                                <div class="card-body">
                                    <h4 class="card-title">Информация <img
                                                src="{{ url('images/icons/icons8-info-48.png') }}"></h4>
                                    <p>
                                        @if ($course->telegram!=null)
                                            <b><img src="{{ url('images/icons/icons8-telegram-app-48.png') }}"
                                                    title="Telegram App"
                                                    width="16"
                                                    height="16"> Чат в телеграм:</b> <a
                                                    href="{{$course->telegram}}">{{$course->telegram}}</a><br/>
                                        @endif
                                    </p>
                                    <p>
                                        <b>Преподаватели:</b>
                                    </p>
                                    <ul class="list-inline">
                                        @foreach($course->teachers as $teacher)
                                            <li class="list-inline-item"><a class="black-link"
                                                                            href="{{url('/insider/profile/'.$teacher->id)}}">{{$teacher->name}}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <p>
                                        <b>Студенты:</b>
                                    </p>
                                    <ul class="list-inline">
                                        @foreach($students as $student)
                                            <li class="list-inline-item"><a class="black-link"
                                                                            href="{{url('/insider/profile/'.$student->id)}}">{{$student->name}}</a>
                                            </li>
                                        @endforeach
                                    </ul>

                                </div>
                            </div>
                            @foreach($available_lessons as $key => $lesson)
                                @if ($lesson->steps->count()!=0)
                                    <div class="card" style="min-width:40% !important;">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col">
                                                    @if ($lessons->contains($lesson))
                                                    <h5>{{$key+1}}. <a class="collection-item"
                                                                       href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                                    </h5>
                                                    @else
                                                        <h5>{{$key+1}}. <a class="collection-item text-muted"
                                                                           href="#">{{$lesson->name}}</a>
                                                        </h5>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col">
                                                    @parsedown($lesson->description)
                                                    @if ($lesson->scale_id != null and $lessons->contains($lesson))
                                                        <a class="btn btn-light btn-sm" data-toggle="modal"
                                                           data-target="#scale{{$lesson->scale_id}}" href="#"
                                                           role="button">Чему я
                                                            учусь?</a>

                                                        <div class="modal fade" id="scale{{$lesson->scale_id}}"
                                                             tabindex="-1"
                                                             role="dialog"
                                                             aria-hidden="true">
                                                            <div class="modal-dialog modal-lg" role="document">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title"><span class="lead">Результаты прохождения урока</span> {{$lesson->name}}
                                                                        </h5>
                                                                        <button type="button" class="close"
                                                                                data-dismiss="modal" aria-label="Close">
                                                                            <span aria-hidden="true">&times;</span>
                                                                        </button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        @include('scales.details_part', ['scale'=>$lesson->scale])
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($lesson->percent($user) > 90)
                                                    <div class="col-sm-auto">
                                                        <img src="{{url($lesson->sticker)}}" style="max-width: 100px;"/>
                                                    </div>
                                                @endif


                                            </div>
                                        </div>
                                        <div class="card-footer">
                                            <div class="row">
                                                <div class="col">
                                                    @if ($lessons->contains($lesson))
                                                        <div class="progress" style="margin: 5px;">
                                                            @if ($lesson->percent($user) < 40)
                                                                <div class="progress-bar progress-bar-striped bg-danger"
                                                                     role="progressbar"
                                                                     style="width: {{$lesson->percent($user)}}%"
                                                                     aria-valuenow="{{$lesson->percent($user)}}"
                                                                     aria-valuemin="0"
                                                                     aria-valuemax="100">{{$lesson->points($user)}}
                                                                    / {{$lesson->max_points($user)}}</div>

                                                            @elseif($lesson->percent($user) < 60)
                                                                <div class="progress-bar progress-bar-striped bg-warning"
                                                                     role="progressbar"
                                                                     style="width: {{$lesson->percent($user)}}%"
                                                                     aria-valuenow="{{$lesson->percent($user)}}"
                                                                     aria-valuemin="0"
                                                                     aria-valuemax="100">
                                                                    Очки опыта: {{$lesson->points($user)}}
                                                                    / {{$lesson->max_points($user)}}</div>

                                                            @else
                                                                <div class="progress-bar progress-bar-striped bg-success"
                                                                     role="progressbar"
                                                                     style="width: {{$lesson->percent($user)}}%"
                                                                     aria-valuenow="{{$lesson->percent($user)}}"
                                                                     aria-valuemin="0"
                                                                     aria-valuemax="100">
                                                                    Очки опыта: {{$lesson->points($user)}}
                                                                    / {{$lesson->max_points($user)}}</div>

                                                            @endif
                                                        </div>
                                                    @else
                                                        <a class="btn btn-success"
                                                           href="{{url('/insider/courses/'.$course->id.'/add_sdl_lesson?lesson_id='.$lesson->id)}}">Добавить!</a>
                                                        @if ($lesson->scale_id != null)
                                                            <a class="btn btn-primary" data-toggle="modal"
                                                               data-target="#scale{{$lesson->scale_id}}" href="#"
                                                               role="button">Чему я
                                                                научусь?</a>

                                                            <div class="modal fade" id="scale{{$lesson->scale_id}}"
                                                                 tabindex="-1"
                                                                 role="dialog"
                                                                 aria-hidden="true">
                                                                <div class="modal-dialog modal-lg" role="document">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title"><span class="lead">Результаты прохождения урока</span> {{$lesson->name}}
                                                                            </h5>
                                                                            <button type="button" class="close"
                                                                                    data-dismiss="modal"
                                                                                    aria-label="Close">
                                                                                <span aria-hidden="true">&times;</span>
                                                                            </button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            @include('scales.details_part', ['scale'=>$lesson->scale])
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <div class="card">
                            <div class="card-body">
                                        <h5><span class="lead">Текущий проект - </span><a
                                                    href="{{url('insider/ideas/'.$idea->id)}}">{{$idea->name}}</a>
                                        </h5>
                            </div>


                            <div class="alchemy" id="alchemy"></div>

                            <div class="card-footer">
                                <a class="float-right btn btn-light btn-sm" style="margin-right: 5px;" href="{{url('/insider/courses/'.$course->id.'/sdl_idea')}}" onclick="return confirm('Вы уверены?')"><i
                                            class="icon ion-alert-circled"></i>&nbsp;Я хочу поменять проект</a>
                            </div>

                            <script src="{{url('js/vendor.js')}}"></script>
                            <script src="{{url('js/alchemy.js')}}"></script>
                            <script type="text/javascript">
                                config = {
                                    directedEdges: true,
                                    fixRootNodes: false,
                                    dataSource: "{{url('/insider/core/'.$user->id.'/node/'.$idea->sdl_node_id)}}",
                                    nodeCaption: 'title',
                                    graphHeight: function () {
                                        return 500;
                                    },
                                    nodeClick: function (d) {
                                        edges = alchemy.getEdges(d.id);
                                        edges.forEach(function (edge) {

                                            alchemy._edges[edge.id][0]._state = 'highlighted';
                                            //alchemy._edges[edge.id][0]._style['opacity']='1';
                                            alchemy._nodes[edge._properties.source]._state = 'highlighted';
                                            //alchemy._nodes[edge._properties.source]._style['opacity']='1';
                                            //console.log(alchemy._nodes[edge._properties.source]);

                                            alchemy._edges[edge.id][0].setStyles()
                                            alchemy._nodes[edge._properties.source].setStyles();
                                        })
                                    },

                                    cluster: false,
                                    alpha: 0,
                                    curvedEdges: true,
                                    forceLocked: true,
                                    nodeTypes: {"nodeType": ["use", "exists", "target"]},
                                    nodeStyle: {
                                        "all": {
                                            "radius": function (d) {
                                                return 40;
                                            }

                                        }
                                    },
                                    clusterColours: ["#1B9E77", "#D95F02", "#7570B3", "#E7298A", "#66A61E", "#E6AB02", "#12AB02", "#99AB02"]
                                };
                                alchemy = new Alchemy(config);
                            </script>

                        </div>
                    @endif
                </div>

            </div>
        </div>


        <div class="tab-pane fade" id="done_lessons" role="tabpanel" aria-labelledby="done_lessons">
            <div class="row">
                <div class="col-md-12">

                    <div class="card-deck">
                        @foreach($done_lessons as $key => $lesson)
                            @if ($lesson->steps->count()!=0)
                                <div class="card" style="min-width:40% !important;">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col">
                                                <h5>{{$key+1}}. <a class="collection-item"
                                                                   href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson->steps->first()->id)}}">{{$lesson->name}}</a>
                                                </h5>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col">
                                                @parsedown($lesson->description)
                                                @if ($lesson->scale_id != null)
                                                    <a class="btn btn-light btn-sm" data-toggle="modal"
                                                       data-target="#scale{{$lesson->scale_id}}" href="#" role="button">Чему
                                                        я
                                                        научился?</a>

                                                    <div class="modal fade" id="scale{{$lesson->scale_id}}"
                                                         tabindex="-1"
                                                         role="dialog"
                                                         aria-hidden="true">
                                                        <div class="modal-dialog modal-lg" role="document">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title"><span class="lead">Результаты прохождения урока</span> {{$lesson->name}}
                                                                    </h5>
                                                                    <button type="button" class="close"
                                                                            data-dismiss="modal" aria-label="Close">
                                                                        <span aria-hidden="true">&times;</span>
                                                                    </button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    @include('scales.details_part', ['scale'=>$lesson->scale])
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($lesson->percent($user) > 90)
                                                <div class="col-sm-auto">
                                                    <img src="{{url($lesson->sticker)}}" style="max-width: 100px;"/>
                                                </div>
                                            @endif


                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="row">
                                            <div class="col">
                                                <div class="progress" style="margin: 5px;">
                                                    @if ($lesson->percent($user) < 40)
                                                        <div class="progress-bar progress-bar-striped bg-danger"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($user)}}%"
                                                             aria-valuenow="{{$lesson->percent($user)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">{{$lesson->points($user)}}
                                                            / {{$lesson->max_points($user)}}</div>

                                                    @elseif($lesson->percent($user) < 60)
                                                        <div class="progress-bar progress-bar-striped bg-warning"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($user)}}%"
                                                             aria-valuenow="{{$lesson->percent($user)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($user)}}
                                                            / {{$lesson->max_points($user)}}</div>

                                                    @else
                                                        <div class="progress-bar progress-bar-striped bg-success"
                                                             role="progressbar"
                                                             style="width: {{$lesson->percent($user)}}%"
                                                             aria-valuenow="{{$lesson->percent($user)}}"
                                                             aria-valuemin="0"
                                                             aria-valuemax="100">
                                                            Очки опыта: {{$lesson->points($user)}}
                                                            / {{$lesson->max_points($user)}}</div>

                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal -->
@endsection
