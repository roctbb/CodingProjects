     @if (\Request::is('insider/*') && $quizer)
     @foreach ($tasks as $key => $task)
     <div class="card">
       <div class="card-header">
         <span class="steps-task-title">{{$task->name}}</span>

         @if ($task->price > 0)
         <img src="{{ url('images/icons/icons8-coins-48.png') }}" class="steps-coins-icon">
         &nbsp;{{$task->price}}
         @endif

         @if ($course->teachers->contains($user) || $user->role=='admin')
         <div class="steps-task-tools">
          <a class="btn btn-danger btn-sm"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
            data-action-method="DELETE"
            data-action-confirm="Вы уверены?"><i class="icon fa-solid fa-xmark"></i></a>
           <a class="btn btn-primary btn-sm steps-task-edit-btn"
             href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"><i
               class="icon fa-solid fa-pen-to-square"></i></a>
          <a class="btn btn-outline-secondary btn-sm"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"
            data-action-method="POST"><i
               class="icon fa-solid fa-wand-magic-sparkles"></i></a>
           <a class="btn btn-outline-secondary btn-sm"
             href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/peer')}}"><i
               class="icon fa-solid fa-user-group"></i></a>


          <a class="btn btn-outline-secondary btn-sm"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"
            data-action-method="POST"><i
               class="icon fa-solid fa-arrow-right"></i></a>
          <a class="btn btn-outline-secondary btn-sm"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"
            data-action-method="POST"><i
               class="icon fa-solid fa-arrow-left"></i></a>
           @if ($step->previousStep() != null)
          <a class="btn btn-outline-secondary btn-sm"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"
            data-action-method="POST"><i
               class="icon fa-solid fa-arrow-up"></i></a>
           @endif
           @if ($step->nextStep() != null)
          <a class="btn btn-outline-secondary btn-sm"
            href="#"
            data-action-url="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"
            data-action-method="POST"><i
               class="icon fa-solid fa-arrow-down"></i></a>
           @endif
         </div>

         @endif

       </div>
       <div class="card-body markdown">
         {!! parsedown_math($task->text) !!}

         @if ($task->is_quiz)
         <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}" method="POST"
           class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center"

           onsubmit="checkTask(event, {{json_encode($task->id)}})"
           >
           {{ csrf_field() }}
           <label for="text{{$task->id}}"><strong>Ответ:&nbsp;</strong></label>
           <input type="text" name="text" class="form-control form-control-sm" id="text{{$task->id}}" />&nbsp;
           <button type="submit" class="btn btn-primary btn-sm">Отправить
           </button>


         </form>
         @if ($errors->has('text'))
         <span class="invalid-feedback d-block"><strong>{{ $errors->first('text') }}</strong></span>
         @endif

         @endif

         <span class="badge text-bg-secondary">Очков опыта: {{$task->max_mark}}</span>
         @if ($task->is_quiz && $task->solutions()->where('user_id', Auth::User()->id)->count()!=0)
         @php
         $solution = $task->solutions()->where('user_id', Auth::User()->id)->orderBy('id', 'DESC')->get()->first();
         @endphp
         <span class="badge text-bg-primary" id="TSK_{{$task->id}}">Очков опыта: {{$solution->mark}}</span>
         <span class="small" id="TSK_COM_{{$task->id}}">{{$solution->comment}}</span>
         @else
         <span class="badge text-bg-primary" id="TSK_{{$task->id}}"></span>
         <span class="small" id="TSK_COM_{{$task->id}}"></span>
         @endif
       </div>
     </div>
     @endforeach
     @endif
