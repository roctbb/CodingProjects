@php
    $course = $courseCard['course'];
    $percent = $courseCard['percent'];
    $overdueCount = $courseCard['overdue_count'];
    $warningCount = $courseCard['warning_count'];
@endphp

<li class="md-course-cards__item">
    <article class="md-course-card-shell" aria-labelledby="active-course-{{ $course->id }}">
<md-outlined-card class="md-surface md-course-card md-surface-card md-course-card--{{ $courseCard['status_class'] }}">
    <a class="md-course-card__stretched-link"
       href="{{ url('insider/courses/'.$course->id) }}"
       aria-label="Открыть курс {{ $course->name }}"></a>
    <header class="md-course-card__head">
        <h3 id="active-course-{{ $course->id }}">
            <a href="{{ url('insider/courses/'.$course->id) }}">{{ $course->name }}</a>
        </h3>
        @php
            $courseCardMenuTriggerId = 'course-card-menu-trigger-'.$course->id;
            $courseCardMenuId = 'course-card-menu-'.$course->id;
        @endphp
        <div class="md-course-card__menu">
            <md-icon-button id="{{ $courseCardMenuTriggerId }}"
                            class="md-course-card__menu-trigger"
                            aria-label="Дополнительные действия"
                            data-md-menu-trigger="{{ $courseCardMenuId }}">
                <i class="fas fa-ellipsis-v" aria-hidden="true"></i>
            </md-icon-button>
            <md-menu id="{{ $courseCardMenuId }}" anchor="{{ $courseCardMenuTriggerId }}" positioning="popover" quick>
                <md-menu-item data-href="{{ url('insider/courses/'.$course->id) }}">
                    <div slot="headline">Страница курса</div>
                </md-menu-item>
                @if ($isManager)
                    <md-menu-item data-href="{{ url('insider/courses/'.$course->id.'/edit') }}">
                        <div slot="headline">Редактировать курс</div>
                    </md-menu-item>
                @endif
                @if ($course->site != null)
                    <md-menu-item data-href="{{ $course->site }}" data-href-target="_blank">
                        <div slot="headline">Сайт курса</div>
                    </md-menu-item>
                @endif
            </md-menu>
        </div>
    </header>

    <p class="md-course-card__meta md-meta-row">
        <md-assist-chip class="md-meta-chip"
                        label="Уроков: {{ $course->program->lessons->count() }}"></md-assist-chip>
        <md-assist-chip class="md-meta-chip"
                        label="Студентов: {{ $course->students->count() }}"></md-assist-chip>
    </p>

    <p class="md-course-card__description">{{ $course->description ?: 'Описание курса пока не добавлено.' }}</p>

    @if ($percent !== null)
        <div class="md-course-progress">
            <md-linear-progress class="md-course-progress-bar"
                               value="{{ max(0, min(100, $percent)) / 100 }}">
            </md-linear-progress>
            <strong>{{ $percent }}%</strong>
        </div>
    @endif

    <div class="md-course-card__chips">
        @if ($overdueCount > 0)
            <span class="md-inline-chip md-inline-chip--danger">Просрочено: {{ $overdueCount }}</span>
        @endif
        @if ($warningCount > 0)
            <span class="md-inline-chip md-inline-chip--warning">Срок скоро: {{ $warningCount }}</span>
        @endif
    </div>

</md-outlined-card>
    </article>
</li>
