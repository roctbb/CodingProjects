  <div style="padding: 15px;">
      @if (\Request::is('insider/*'))
      <small><a href="{{url('/insider/courses/'.$course->id)}}" style="font-weight: 300;">{{$course->name}}</a> &raquo;
          <strong>{{$step->lesson->name}}</strong></small>
      <h2 style="font-weight: 300;">{{$step->name}}</h2>
      @endif
      @if (\Request::is('open/*'))
      <small>
          <strong>{{$step->lesson->name}}</strong></small>
      <h2 style="font-weight: 300;">{{$step->name}}</h2>
      @endif
  </div>