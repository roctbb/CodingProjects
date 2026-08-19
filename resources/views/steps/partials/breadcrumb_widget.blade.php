  <div class="step-page-header">
      @if (\Request::is('insider/*'))
      <div class="step-page-header__crumb">
          <a href="{{url('/insider/courses/'.$course->id)}}">{{$course->name}}</a>
          <i class="fas fa-chevron-right small opacity-50"></i>
          <a href="{{url('/insider/courses/'.$course->id.'?chapter='.$step->lesson->chapter->id)}}">{{$step->lesson->name}}</a>
      </div>
      <h1 class="step-page-header__title">{{$step->name}}</h1>
      @endif
      @if (\Request::is('open/*'))
      <div class="step-page-header__crumb">
          <a href="{{url('/open/steps/'.$step->lesson->steps->first()->id)}}">{{$step->lesson->name}}</a>
      </div>
      <h1 class="step-page-header__title">{{$step->name}}</h1>
      @endif
  </div>
