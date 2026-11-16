<x-gc-roster id="review-queue-picker" labelledby="review-queue-title">
<div class="gc-card gc-roster-surface gc-review-queue">
    <div class="gc-panel-heading"><h2 id="review-queue-title">Очередь</h2><span class="gc-meta-label ms-auto">{{ $reviewQueue->total() }}</span><button class="btn-close d-md-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#review-queue-picker" aria-label="Закрыть очередь проверки"></button></div>
    <div class="px-3 pb-2 gc-summary-line">
        <span class="gc-help-text">{{ $reviewQueueAll ? 'Все мои курсы' : $course->name }}</span>
        @if($reviewQueue->total())
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с очередью"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <form method="post" action="{{ url('/insider/reviews/reset-pending') }}" data-confirm="Пропустить все {{ $reviewQueue->total() }} непроверенных решений, соответствующих фильтрам?">
                        @csrf
                        @if(!$reviewQueueAll)<input type="hidden" name="course" value="{{ $course->id }}">@endif
                        @if(!empty($reviewFilters['lesson']))<input type="hidden" name="lesson" value="{{ $reviewFilters['lesson'] }}">@endif
                        <input type="hidden" name="q" value="{{ $reviewFilters['q'] }}"><input type="hidden" name="review_status" value="{{ $reviewFilters['review_status'] }}">
                        <button class="dropdown-item" type="submit">Пропустить список · {{ $reviewQueue->total() }}</button>
                    </form>
                </div>
            </div>
        @endif
    </div>
    @include('steps.partials.review-filters', ['embedded' => true])
    <nav class="gc-review-queue__items" aria-label="Непроверенные решения">
    @forelse($reviewQueue as $queued)
        @php
            $queuedUrl = app(\App\Services\TaskLocation::class)->solution($queued, $queueParameters ?? []);
        @endphp
        <a class="gc-review-queue__item @if($queued->id === $currentQueuedId) is-current @endif" href="{{ $queuedUrl }}" data-review-queue-solution="{{ $queued->id }}" @if($queued->id === $currentQueuedId) aria-current="true" @endif>
            <x-gc-avatar :frame="false" :user="$queued->user" size="sm" alt="" /><span class="min-width-0"><strong>{{ $queued->user->name }}</strong><span>{{ $queued->task->name ?? 'Задача' }}</span>@if(isset($queued->course))<small>{{ $queued->course->name }}</small>@endif<small>@if($queued->recheck_requested)<span class="gc-task-state gc-task-state--pending">Перепроверка</span> · @endif{{ $queued->submitted?->format('d.m H:i') }}</small></span>
        </a>
    @empty<p class="gc-help-text p-3">Очередь пуста. Открыта история работы ученика.</p>@endforelse
    </nav>
    @if($reviewQueue->hasPages())<div class="gc-panel-body gc-summary-line"><span class="gc-help-text">{{ $reviewQueue->currentPage() }} / {{ $reviewQueue->lastPage() }}</span><x-gc-page-navigation :paginator="$reviewQueue" label="Страницы очереди" /></div>@endif
</div>
</x-gc-roster>
