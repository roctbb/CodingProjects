@php
    $variant = $variant ?? 'active';
    $showProgress = $showProgress ?? false;
    $isLinked = $isLinked ?? true;
    $href = $href ?? url('insider/courses/'.$course->id);
    $progress = $showProgress ? $courseProgress->get($course->id) : null;
    $percent = $showProgress && empty($isTeacher) ? min(100, round(optional($progress)->percent ?? 0)) : null;
    $isArchive = $variant === 'archive';
    $isAvailable = $variant === 'available';
    $courseIcon = \App\Support\CourseIcons::forCourse($course);
    $statusLabel = [
        'draft' => 'Черновик',
        'archive' => 'Архив',
        'available' => $course->mode == 'open' ? 'Открытый' : 'По инвайту',
    ][$variant] ?? null;
@endphp

<div class="col">
    <article class="course-index-card gc-card">
    @if($isLinked)
        <a href="{{ $href }}" class="course-index-card__body gc-card-link">
    @else
        <div class="course-index-card__body">
    @endif
            <div class="course-index-card__content">
                <x-gc-course-icon :name="$courseIcon" class="course-index-card__icon course-index-card__icon--{{ $courseIcon }}" />
                <div class="course-index-card__head">
                    <h6 class="course-index-card__title">{{ $course->name }}</h6>
                    @if($statusLabel)
                        <span class="badge rounded-pill bg-body-tertiary flex-shrink-0">{{ $statusLabel }}</span>
                    @endif
                </div>
                <p class="course-index-card__description">{{ \Illuminate\Support\Str::limit($course->description, 110) }}</p>
            </div>
            @if ($percent !== null)
                <div class="course-index-card__progress">
                    <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                        <small class="text-muted">Прогресс</small>
                        <small class="text-muted text-nowrap">
                            {{ $percent }}%
                            @if($progress && $progress->max_points)
                                · {{ $progress->points }}/{{ $progress->max_points }} XP
                            @endif
                        </small>
                    </div>
                    <x-gc-progress :percent="$percent" height="5px" />
                </div>
            @endif

            @if($isArchive || $isAvailable)
                <div class="course-index-card__footer">
                    @if($isArchive)
                    <div class="d-flex align-items-center gap-2 min-width-0 text-muted small">
                        <i class="far fa-calendar-alt"></i>
                        <span class="text-truncate">
                            @if ($course->start_date && $course->end_date)
                                {{ $course->start_date->format('d.m.Y') }} - {{ $course->end_date->format('d.m.Y') }}
                            @elseif ($course->start_date)
                                с {{ $course->start_date->format('d.m.Y') }}
                            @elseif ($course->end_date)
                                до {{ $course->end_date->format('d.m.Y') }}
                            @else
                                Даты не указаны
                            @endif
                        </span>
                    </div>
                    @endif

                    @if($isAvailable)
                        <span class="text-muted small text-truncate">{{ $course->level ? $course->level : 'Курс' }}</span>
                        @if($course->mode == 'open')
                            <a href="{{ url('insider/courses/'.$course->id.'/enroll') }}" class="btn btn-success btn-sm rounded-3 flex-shrink-0">Записаться</a>
                        @else
                            <span class="gc-meta-label flex-shrink-0">Инвайт</span>
                        @endif
                    @endif
                </div>
            @endif
    @if($isLinked)
        </a>
    @else
        </div>
    @endif
    </article>
</div>
