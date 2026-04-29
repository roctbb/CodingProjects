  <div class="p-3">
      @if (\Request::is('insider/*'))
      <small><a href="{{url('/insider/courses/'.$course->id)}}" class="font-weight-light">{{$course->name}}</a> &raquo;
          <strong>{{$step->lesson->name}}</strong></small>
      <h2 class="font-weight-light">{{$step->name}}</h2>
      @endif
      @if (\Request::is('open/*'))
      <small>
          <strong>{{$step->lesson->name}}</strong></small>
      <h2 class="font-weight-light">{{$step->name}}</h2>
      @endif
  </div>
