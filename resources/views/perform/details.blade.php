@extends('layouts.empty')

@section('title')
    GeekClass: "{{$course->name}}" - "{{$step->name}}"
@endsection

@section('tabs')

@endsection

@section('content')
    <div class="row">
        <div class="col-md-8">

            <h2><span style="font-weight: 200;"><a style="display: inline;" class="nav-link" role="tab" id="back-link"
                                                   href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id)}}"><i
                                class="icon ion-chevron-left"></i></a>{{$course->name}}
                    - </span>{{$step->lesson->name}}</h2>
        </div>
    </div>

    <div class="row" style="margin-top: 15px;">
        <div class="col-md-12">
            @if (count($tasks)!=0)
                <div class="row" style=" margin-bottom: 15px;">
                    <div class="col">
                        <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                            @if (count($tasks)!=0 && !$zero_theory)
                                <li class="nav-item">
                                    <a class="nav-link active" id="theory-tab" data-toggle="pill" href="#theory"
                                       role="tab"
                                       aria-controls="theory" aria-expanded="true">0. Теория</a>
                                </li>
                            @endif
                            @if (!$one_tasker || !$zero_theory)
                                @foreach ($tasks as $key => $task)
                                    <li class="nav-item">
                                        <a class="nav-link" id="tasks-tab{{$task->id}}"
                                           data-toggle="pill"
                                           href="#task{{$task->id}}"
                                           role="tab"
                                           aria-controls="tasks{{$task->id}}" aria-expanded="true">{{$key+1}}
                                            . {{$task->name}}
                                            @if($task->is_star) <sup>*</sup> @endif
                                            @if($task->only_class) <sup><i
                                                        class="icon ion-android-contacts"></i></sup> @endif
                                            @if($task->only_remote) <sup><i class="icon ion-at"></i></sup> @endif</a>
                                    </li>
                                @endforeach
                            @endif

                        </ul>
                    </div>


                </div>
            @endif
            <div class="tab-content" id="pills-tabContent" style="margin-bottom: 15px;">
                @if ($empty || !$zero_theory)
                    <div class="tab-pane fade show active" id="theory" role="tabpanel" aria-labelledby="v-theory-tab">
                        <div class="row">
                            <div class="col">
                                <div class="card">
                                    <div class="card-body markdown perform">
                                        <h4 class="lead">{{$step->name}}</h4>
                                        @parsedown($step->theory)
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
                @foreach ($tasks as $key => $task)
                    <div class="tab-pane fade" id="task{{$task->id}}" role="tabpanel"
                         aria-labelledby="tasks-tab{{$task->id}}">


                        <div class="row">
                            <div class="col">
                                @if ($task->is_star)
                                    <div class="alert alert-success" role="alert">
                                        <strong>Это необязательная задача.</strong> За ее решение вы получите
                                        дополнительные
                                        баллы.
                                    </div>
                                @endif

                                <div class="card">
                                    <div class="card-header">
                                        {{$task->name}}

                                    </div>
                                    <div class="card-body markdownn perform">
                                        @parsedown($task->text)

                                        <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <script>
                    $('.tab-pane').first().removeClass('fade');
                    $('.tab-pane').first().addClass('show active');
                </script>

                <p>
                    @if ($step->previousStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->previousStep()->id)}}"
                           class="btn btn-success btn-sm">Назад</a>
                    @endif
                    @if ($step->nextStep() != null)
                        <a href="{{url('/insider/courses/'.$course->id.'/perform/'.$step->nextStep()->id)}}"
                           class="btn btn-success btn-sm float-right">Вперед</a>
                    @endif
                </p>
            </div>
        </div>
    </div>




@endsection
