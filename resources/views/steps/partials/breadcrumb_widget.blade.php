  <div class="step-page-header">
      @if (\Request::is('insider/*'))
      <div class="step-page-header__crumb">
          <a href="{{url('/insider/courses/'.$course->id)}}">{{$course->name}}</a>
          <i class="icon ion-chevron-right small opacity-50"></i>
          <span>{{$step->lesson->name}}</span>
      </div>
      <h1 class="step-page-header__title">{{$step->name}}</h1>
      @endif
      @if (\Request::is('open/*'))
      <div class="step-page-header__crumb">
          <span>{{$step->lesson->name}}</span>
      </div>
      <h1 class="step-page-header__title">{{$step->name}}</h1>
      @endif
  </div>
