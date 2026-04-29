<nav class="col-sm-4 col-md-3 col-xl-2 sidebar" id="stepsSidebar">

            <ul class="nav nav-pills flex-column">
                @if (\Request::is('insider/*'))
                    <li class="nav-item">
                        <a class="nav-link h4 py-2 mb-0"
                           href="{{url('/insider/courses/'.$course->id.'?chapter='.$step->lesson->chapter->id)}}">
                             <i class="icon ion-chevron-left"></i> <img src="{{url('images/bhlogo.png')}}"
                                                                        height="35" alt=""/> </a>
                    </li>
                @endif
                @if (\Request::is('open/*'))
                    <li class="nav-item">
                         <span class="nav-link h4 py-2 mb-0">
                              <img src="{{url('images/bhlogo.png')}}" height="35" alt=""/> </span>
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
                 <p class="mt-3 text-center">
                    <a href="{{url('/insider/courses/'.$course->id.'/lessons/'.$step->lesson->id.'/create')}}"
                       class="btn btn-success btn-sm">Новый
                        этап</a>
                </p>
            @endif
        </nav>
