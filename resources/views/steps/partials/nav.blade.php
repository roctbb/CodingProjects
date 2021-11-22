<nav class="col-sm-4 col-md-3 col-xl-2 sidebar" id="stepsSidebar">

            <ul class="nav nav-pills flex-column">
                @if (\Request::is('insider/*'))
                    <li class="nav-item">
                    <!--<a class="nav-link" style="padding-top: 10px; padding-bottom: 10px; font-size: 150%;"
                           href="{{url('/insider/courses/'.$course->id)}}">
                            <i class="icon ion-chevron-left"></i> GeekClass </a>-->
                        <a class="nav-link" style="padding-top: 10px; padding-bottom: 10px; font-size: 150%;"
                           href="{{url('/insider/courses/'.$course->id.'?chapter='.$step->lesson->chapter->id)}}">
                            <i class="icon ion-chevron-left"></i> <img src="{{url('images/bhlogo.png')}}"
                                                                       style="height: 35px;"/> </a>
                    </li>
                @endif
                @if (\Request::is('open/*'))
                    <li class="nav-item">
                        <a class="nav-link" style="padding-top: 10px; padding-bottom: 10px; font-size: 150%;"
                           href="#">
                            <img src="{{url('images/bhlogo.png')}}" style="height: 35px;"/> </a>
                    </li>
                @endif
            </ul>
            <ul class="nav nav-pills flex-column">

                @foreach($step->lesson->steps as $lesson_step)
                    <li class="nav-item">
                        @if (\Request::is('insider/*'))
                            <a class="nav-link @if ($lesson_step->id==$step->id) active @endif"
                               href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson_step->id)}}">{{$lesson_step->name}}
                                @if ($lesson_step->tasks->count()!=0)
                                    <i class="ion ion-trophy"></i>
                                @endif
                            </a>

                        @endif
                        @if (\Request::is('open/*'))
                            <a class="nav-link @if ($lesson_step->id==$step->id) active @endif"
                               href="{{url('/open/steps/'.$lesson_step->id)}}">{{$lesson_step->name}}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))
                <p align="center" style="margin-top: 15px;">
                    <a href="{{url('/insider/courses/'.$course->id.'/lessons/'.$step->lesson->id.'/create')}}"
                       class="btn btn-success btn-sm">Новый
                        этап</a>
                </p>
            @endif
        </nav>