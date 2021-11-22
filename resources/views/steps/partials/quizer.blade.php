     @if (\Request::is('insider/*') && $quizer)
     @foreach ($tasks as $key => $task)
     <div class="card">
       <div class="card-header">
         {{$task->name}}&nbsp;&nbsp;
         @foreach($task->consequences as $consequence)
         @if (!$user->checkPrerequisite($consequence))
         <span class="badge badge-secondary">{{$consequence->title}}</span>
         @else
         <span class="badge badge-success">{{$consequence->title}}</span>
         @endif
         @endforeach

         @if ($task->price > 0)
         <img src="{{ url('images/icons/icons8-coins-48.png') }}" style="height: 23px;">
         &nbsp;{{$task->price}}
         @endif

         @if ($course->teachers->contains($user) || $user->role=='admin')
         <a class="float-right btn btn-danger btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
           onclick="return confirm('Вы уверены?')"><i class="icon ion-android-close"></i></a>
         <a style="margin-right: 5px;" class="float-right btn btn-success btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"><i
             class="icon ion-android-create"></i></a>
         <a class="float-right btn btn-default btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"><i
             class="icon ion-ios-color-wand"></i></a>
         <a class="float-right btn btn-default btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/peer')}}"><i
             class="icon ion-person-stalker"></i></a>


         <a class="float-right btn btn-default btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"><i
             class="icon ion-arrow-right-c"></i></a>
         <a class="float-right btn btn-default btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"><i
             class="icon ion-arrow-left-c"></i></a>
         @if ($step->previousStep() != null)
         <a class="float-right btn btn-default btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"><i
             class="icon ion-arrow-up-c"></i></a>
         @endif
         @if ($step->nextStep() != null)
         <a class="float-right btn btn-default btn-sm"
           href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"><i
             class="icon ion-arrow-down-c"></i></a>
         @endif

         @endif

       </div>
       <div class="card-body markdown">
         @parsedown($task->text)

         @if ($task->is_quiz)
         <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}" method="POST"
           class="form-inline"
           
           onsubmit="checkTask(event, {{json_encode($task->id)}})"
           >
           {{ csrf_field() }}
           <label for="text{{$task->id}}"><strong>Ответ:&nbsp;</strong></label>
           <input type="text" name="text" class="form-control form-control-sm" id="text{{$task->id}}" />&nbsp;
           <button type="submit" class="btn btn-success btn-sm">Отправить
           </button>


         </form>
         @if ($errors->has('text'))
         <br><span class="help-block error-block"><strong>{{ $errors->first('text') }}</strong></span>
         @endif

         @endif

         <span class="badge badge-secondary">Очков опыта: {{$task->max_mark}}</span>
         @if ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
         @php
         $solution = $task->solutions()->where('user_id', Auth::User()->id)->orderBy('id', 'DESC')->get()->first();
         @endphp
         <span class="badge badge-primary" id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
         <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
         @else
         <span class="badge badge-primary" id="TSK_{{$task->id}}"></span>
         <span class="small" id="TSK_COM_{{$task->id}}"></span>
         @endif
       </div>
     </div>
     @endforeach
     @endif