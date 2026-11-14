  <div class="step-page-header">
      @if (\Request::is('insider/*'))
      <div class="step-page-header__crumb">
          <a href="{{url('/insider/courses/'.$course->id)}}">{{$course->name}}</a>
          <i class="fas fa-chevron-right small opacity-50"></i>
          <a href="{{url('/insider/courses/'.$course->id.'?chapter='.$step->lesson->chapter->id)}}">{{$step->lesson->name}}</a>
      </div>
      @endif
      @if (\Request::is('open/*'))
      <div class="step-page-header__crumb">
          <a href="{{url('/open/steps/'.$step->lesson->steps->first()->id)}}">{{$step->lesson->name}}</a>
      </div>
      @endif
      <div class="step-page-header__heading">
          <h1 class="step-page-header__title">{{$step->name}}</h1>
          @if($isManager || ($step->is_notebook && filled($step->theory)))
          <div class="gc-actions gc-workbook-heading-actions">
              @if($isManager)
                  @include('steps.partials.screen-actions')
                  @if(!$hasStepNavigation)
                      @include('steps.partials.add_step', ['compact' => true])
                  @endif
              @endif
              @if ($step->is_notebook && filled($step->theory))
                  <a href="{{ url((\Request::is('insider/*') ? '/insider/courses/'.$course->id.'/steps/' : '/open/steps/').$step->id.'/notebook') }}"
                     class="btn btn-outline-secondary btn-sm flex-shrink-0" download>
                      <i class="fas fa-download me-1" aria-hidden="true"></i> Скачать .ipynb
                  </a>
              @endif
          </div>
          @endif
      </div>
  </div>
