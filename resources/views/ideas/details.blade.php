@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$idea->name}}"
@endsection

@section('tabs')

@endsection


@section('content')

    <div class="row">
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

        <div class="col-md-12">
            @if (!$idea->is_approved and $user->id == $idea->author->id)
                <div class="alert alert-warning" role="alert">
                    Ваша идея ожидает утверждения. Скоро один из преподавателей ознакомится с ней, утвердит ее или
                    предложит доработки.
                </div>
            @endif
            <div class="card">

                <div class="card-body">
                    <div class="row">
                        <div class="col col-md-auto">
                            <h3 class="card-title" style="line-height: 50px; font-weight: 200;">{{$idea->name}}
                                &nbsp;<img src="{{ url('images/icons/icons8-idea-sharing-48.png') }}">
                            </h3>

                            @if ($idea->is_approved) <p class="text-muted">
                                Автор идеи - <a
                                        href="{{url('insider/profile/'.$idea->author->id)}}">{{$idea->author->name}}</a>
                                </h4> @endif

                        </div>
                        <div class="col">

                            @if ($user->role=='teacher' || $user->role=='admin' || ($user->id == $idea->author->id && !$idea->is_approved))
                                <div class="float-right">
                                    @if (($user->role=='teacher' || $user->role=='admin') and !$idea->is_approved)
                                        <a href="{{url('/insider/ideas/'.$idea->id.'/approve')}}"
                                           class="btn btn-primary btn-sm">Утвердить</a>
                                        <a href="{{url('/insider/ideas/'.$idea->id.'/decline')}}"
                                           class="btn btn-warning btn-sm">Попросить доработать</a>
                                    @endif
                                    <a href="{{url('/insider/ideas/'.$idea->id.'/edit')}}"
                                       class="btn btn-success btn-sm"><i class="icon ion-android-create"></i></a>
                                    <a href="{{url('/insider/ideas/'.$idea->id.'/delete')}}"
                                       class="btn btn-danger btn-sm" onclick="return confirm('Вы уверены?')"><i
                                                class="ion-close-round"></i></a>

                                </div>

                            @endif
                        </div>
                    </div>
                    <blockquote class="bd-callout bd-callout-info">
                        <p class="text-muted">{{$idea->short_description}}</p>
                    </blockquote>
                    <div class="markdown">

                        @parsedown($idea->description)
                    </div>
                    @if ($idea->sdl_node_id != null)
                        <blockquote class="bd-callout bd-callout-primary">
                            <p><i>Псс, кажется, для это проекта есть готовый сценарий обучения. Учебные программы, в
                                    которых он доступен перечислены ниже.</i></p>
                            <ul>
                                <li>Самоуправляемые курсы (SDL).</li>
                                @foreach($idea->sdl_node->lessons as $lesson)
                                    <li>{{$lesson->program->name}}.</li>
                                @endforeach
                            </ul>
                        </blockquote>

                    @endif

                </div>
                @if ($idea->sdl_node_id != null)

                    <div class="alchemy" id="alchemy"></div>

                    <script src="{{url('js/vendor.js')}}"></script>
                    <script src="{{url('js/alchemy.js')}}"></script>
                    <script type="text/javascript">
                        config = {
                            directedEdges: true,
                            fixRootNodes: false,
                            dataSource: "{{url('/insider/core/'.$user->id.'/node/'.$idea->sdl_node_id)}}",
                            nodeCaption: 'title',
                            graphHeight: function () {
                                return 700;
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
                @endif

            </div>

        </div>

    </div>





@endsection
