<nav class="steps-sidebar" id="stepsSidebar">
    <div class="steps-sidebar-card card border-0">
        <div class="card-body p-3 p-lg-4">
            <ul class="nav flex-column mb-3">
                @if (\Request::is('insider/*'))
                    <li class="nav-item">
                        <a class="nav-link steps-brand-link"
                           href="{{url('/insider/courses/'.$course->id.'?chapter='.$step->lesson->chapter->id)}}">
                            <span class="steps-brand-top">
                                <i class="icon fa-solid fa-chevron-left"></i>
                                <span>К курсу</span>
                            </span>
                            <span class="steps-brand-course">{{ \Illuminate\Support\Str::limit($course->name, 38) }}</span>
                        </a>
                    </li>
                @endif
                @if (\Request::is('open/*'))
                    <li class="nav-item">
                        <div class="nav-link steps-brand-link steps-brand-link--static" role="presentation">
                            <span class="steps-brand-top">
                                <i class="icon fa-solid fa-book-open"></i>
                                <span>Открытый урок</span>
                            </span>
                            <span class="steps-brand-course">{{ \Illuminate\Support\Str::limit($step->lesson->name, 38) }}</span>
                        </div>
                    </li>
                @endif
            </ul>

            <div class="steps-sidebar-label">Этапы урока</div>
            <ul class="nav flex-column steps-nav-list">
                @foreach($step->lesson->steps as $lesson_step)
                    <li class="nav-item">
                        @if (\Request::is('insider/*'))
                            <a class="nav-link @if ($lesson_step->id==$step->id) active @endif"
                               title="{{ $lesson_step->name }}"
                               href="{{url('/insider/courses/'.$course->id.'/steps/'.$lesson_step->id)}}">
                                <span class="steps-nav-item-main">
                                    <span class="steps-nav-item-index">{{ $loop->iteration }}</span>
                                    <span class="steps-nav-item-name">{{ \Illuminate\Support\Str::limit($lesson_step->name, 38) }}</span>
                                </span>
                                @if ($lesson_step->tasks->count()!=0)
                                    <span class="steps-nav-item-badge">{{$lesson_step->tasks->count()}}</span>
                                @endif
                            </a>
                        @endif
                        @if (\Request::is('open/*'))
                            <a class="nav-link @if ($lesson_step->id==$step->id) active @endif"
                               title="{{ $lesson_step->name }}"
                               href="{{url('/open/steps/'.$lesson_step->id)}}">
                                <span class="steps-nav-item-main">
                                    <span class="steps-nav-item-index">{{ $loop->iteration }}</span>
                                    <span class="steps-nav-item-name">{{ \Illuminate\Support\Str::limit($lesson_step->name, 38) }}</span>
                                </span>
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if (\Request::is('insider/*') && ($course->teachers->contains($user) || $user->role=='admin'))
                <div class="steps-new-stage-wrap">
                    <a href="{{url('/insider/courses/'.$course->id.'/lessons/'.$step->lesson->id.'/create')}}"
                       class="btn btn-primary w-100">Новый этап</a>
                </div>
            @endif
        </div>
    </div>
</nav>
