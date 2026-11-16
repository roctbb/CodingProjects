@if($recentReviews->isNotEmpty())
    <section class="gc-card gc-review-activity" aria-labelledby="recent-review-heading">
        <div class="gc-panel-heading"><h2 class="h6 mb-0" id="recent-review-heading">Последние сдачи</h2><span class="gc-meta-label ms-auto">{{ $recentReviews->firstItem() }}–{{ $recentReviews->lastItem() }} из {{ $recentReviews->total() }}</span></div>
        <ul class="list-unstyled mb-0">
            @foreach($recentReviews as $item)
                @php
                    [$stateLabel, $stateIcon] = match($item->status) {
                        'pending' => [$item->recheck ? 'Перепроверка' : 'Ожидает оценки', 'fa-hourglass-half'],
                        'graded' => ['Проверено', 'fa-check'],
                        'error' => ['Ошибка проверки', 'fa-triangle-exclamation'],
                        'skipped' => ['Пропущено', 'fa-forward'],
                        default => [$item->run_state === 'queued' ? 'В очереди автопроверки' : 'Проверяется', 'fa-rotate'],
                    };
                @endphp
                <li class="gc-review-activity__item" data-review-activity="{{ $item->kind }}-{{ $item->item_id }}">
                    <a href="{{ $item->url }}" class="gc-review-activity__row">
                        <span class="gc-review-activity__body"><strong>{{ $item->student_name }}</strong><span>{{ $item->task_name }}</span><small>{{ $item->course_name }} · {{ $item->lesson_name }}</small></span>
                        <span class="gc-review-activity__result">
                            <span class="gc-review-activity__state is-{{ $item->status }}"><i class="fas {{ $stateIcon }}" aria-hidden="true"></i> {{ $stateLabel }}</span>
                            @if($item->points !== null)<span class="gc-review-activity__score">{{ (float)$item->points }} / {{ $item->max_mark }} баллов</span>@endif
                            @if($item->xp !== null && ($item->points === null || (float)$item->xp !== (float)$item->points))<small>Зачтено {{ (float)$item->xp }} XP</small>@endif
                            <time datetime="{{ $item->sent_at->toIso8601String() }}">{{ $item->sent_at->format($item->sent_at->year === now()->year ? 'd.m H:i' : 'd.m.Y H:i') }}</time>
                        </span>
                    </a>
                    <x-gc-remove-attempt :course="$item->course_id" :task="$item->task_id" :attempt="$item->kind === 'submission' ? $item->item_id : 'solution-'.$item->item_id" :student="$item->student_name" :task-name="$item->task_name" />
                </li>
            @endforeach
        </ul>
        @if($recentReviews->hasPages())<div class="gc-panel-body"><x-gc-page-navigation :paginator="$recentReviews" label="Страницы последних сдач" /></div>@endif
    </section>
@else
    <section class="gc-card gc-empty-state"><div class="gc-empty-icon"><i class="fas fa-inbox" aria-hidden="true"></i></div><h2 class="h6">{{ $reviewFiltered ? 'По этим фильтрам сдач нет' : 'Сдач пока нет' }}</h2><p>Здесь появятся отправленные решения по доступным вам курсам.</p>@if($reviewFiltered)<a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/reviews?view=recent') }}">Сбросить фильтры</a>@endif</section>
@endif
