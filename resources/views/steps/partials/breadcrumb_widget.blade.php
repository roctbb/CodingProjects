<div class="steps-breadcrumb card border-0">
    <div class="card-body">
        @if (\Request::is('insider/*'))
            <small class="steps-breadcrumb-line">
                <a href="{{url('/insider/courses/'.$course->id)}}" class="steps-breadcrumb-link">{{$course->name}}</a>
                <span class="steps-breadcrumb-sep">/</span>
                <strong>{{$step->lesson->name}}</strong>
            </small>
            <h2 class="steps-breadcrumb-title">{{$step->name}}</h2>
        @endif
        @if (\Request::is('open/*'))
            <small class="steps-breadcrumb-line">
                <strong>{{$step->lesson->name}}</strong>
            </small>
            <h2 class="steps-breadcrumb-title">{{$step->name}}</h2>
        @endif
    </div>
</div>
