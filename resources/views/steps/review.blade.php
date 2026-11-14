@extends('layouts.left-menu')

@section('title')
    {{$student->name}}: {{$task->name}}
@endsection

@section('head')
    @include('layouts.partials.mathjax')
@endsection

@section('content')
    @php
        $pendingAttempts = $solutions->filter(fn ($solution) => $solution->isPendingReview());
        $pendingSolutionsCount = $pendingAttempts->count();
        $checkedSolutionsCount = $solutions->count() - $pendingSolutionsCount;
        $conditionOpen = $solutions->isEmpty();
        $reviewFilters = $reviewFilters ?? ['q' => '', 'review_status' => 'all'];
        $queueParameters = array_filter(['q' => $reviewFilters['q'] ?: null, 'review_status' => $reviewFilters['review_status'] !== 'all' ? $reviewFilters['review_status'] : null, 'queue' => $reviewQueueAll ? 'all' : null, 'page' => $reviewQueue->currentPage() > 1 ? $reviewQueue->currentPage() : null], fn ($value) => $value !== null);
        $queueQuery = $queueParameters ? '?'.http_build_query($queueParameters) : '';
        $activeSolutions = $pendingAttempts->take(1);
        if ($activeSolutions->isEmpty()) $activeSolutions = $solutions->take(1);
        $historySolutions = $solutions->reject(fn ($solution) => $activeSolutions->contains('id', $solution->id));
        $attemptNumbers = $solutions->values()->mapWithKeys(fn ($solution, $index) => [$solution->id => $solutions->count() - $index]);
        $currentQueuedId = $reviewQueue->first(fn ($queued) => $queued->task_id == $task->id && $queued->user_id == $student->id && $queued->course_id == $course->id)?->id;
        $isBlocked = ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin') && $task->isBlocked($student->id, $course->id);
        $nativeReview = \Illuminate\Support\Facades\Schema::hasTable('code_task_checkers') && \App\CodeTaskChecker::whereKey($task->id)->exists();
        if ($nativeReview) {
            $nativeSolutionIds = \App\CodeSubmission::where('course_id', $course->id)->where('task_id', $task->id)->where('user_id', $student->id)->pluck('solution_id')->filter()->all();
            $activeSolutions = $activeSolutions->whereNotIn('id', $nativeSolutionIds);
            $historySolutions = $historySolutions->whereNotIn('id', $nativeSolutionIds);
        }
    @endphp
    <div class="container-fluid px-0 solution-review-page gc-stack">
        <div class="gc-page-masthead">
        <x-gc-page-header title="Проверка" mark="code-branch" :description="$course->name">
            <x-slot:breadcrumb><a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step->id.'#task'.$task->id) }}">← К задаче</a></x-slot:breadcrumb>
            <x-slot:summary>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <a class="gc-quiet-link" href="{{ url('/insider/profile/'.$student->id) }}">{{ $student->name }}</a>
                    <span class="gc-task-state gc-task-state--pending">К проверке {{ $pendingSolutionsCount }}</span>
                    <span class="gc-meta-label">Проверено {{ $checkedSolutionsCount }} / {{ $solutions->count() }}</span>
                </div>
            </x-slot:summary>
            <button class="btn btn-outline-secondary btn-sm gc-roster-toggle gc-review-queue-toggle d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#review-queue-picker" aria-controls="review-queue-picker" aria-haspopup="dialog">Очередь <span class="gc-meta-label">{{ $reviewQueue->total() }}</span></button>
            @if($nextReview ?? null)<a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/courses/'.$nextReview->course_id.'/tasks/'.$nextReview->task_id.'/student/'.$nextReview->user_id.$queueQuery.'#solution-'.$nextReview->id) }}">Дальше <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i></a>@endif
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-bs-toggle="dropdown" aria-label="Другие действия с решением" aria-expanded="false"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/assessments') }}">Журнал курса</a>
                    <a class="dropdown-item" href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit') }}">Редактировать задачу</a>
                    @if ($course->teachers->contains(Auth::user()) || Auth::user()->role == 'admin')
                        @if($pendingSolutionsCount)
                            <form method="POST" action="{{ url('insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id.'/skip-review') }}" data-confirm="Пропустить все непроверенные решения этого ученика по задаче?">
                                @csrf
                                <button type="submit" class="dropdown-item">Пропустить непроверенные решения</button>
                            </form>
                        @endif
                        @if($isBlocked)
                            <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/unblock/'.$student->id) }}" data-confirm="Разблокировать задачу для этого ученика?">@csrf<button class="dropdown-item" type="submit">Разблокировать задачу</button></form>
                        @else
                            <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/block/'.$student->id) }}" data-confirm="Заблокировать задачу для этого ученика? Все предыдущие баллы будут обнулены.">@csrf<button class="dropdown-item text-danger" type="submit">Заблокировать задачу</button></form>
                        @endif
                    @endif
                </div>
            </div>
        </x-gc-page-header>

        </div>
        <div class="gc-master-detail gc-roster-workspace">
            @include('steps.partials.review-queue')
            <main class="gc-master-detail__content gc-stack" aria-label="Открытая работа">
        <div class="gc-card solution-task-card">
            <div class="gc-section-header gc-section-header--between">
                <div class="solution-task-card__title">
                    <strong class="d-block">{{ $task->name }}</strong>
                    <span class="small text-muted">Условие задачи</span>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    <span class="badge rounded-pill bg-body-tertiary text-nowrap">{{ $task->max_mark }} XP</span>
                    <button class="btn btn-sm gc-action-button solution-action solution-task-toggle"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#task-condition-{{ $task->id }}"
                            aria-expanded="{{ $conditionOpen ? 'true' : 'false' }}"
                            aria-label="Условие задачи"
                            aria-controls="task-condition-{{ $task->id }}">
                        <i class="fas fa-chevron-up" aria-hidden="true"></i>
                        <span class="solution-task-toggle__expanded">Свернуть</span>
                        <span class="solution-task-toggle__collapsed">Показать</span>
                    </button>
                </div>
            </div>
            <div class="collapse {{ $conditionOpen ? 'show' : '' }}" id="task-condition-{{ $task->id }}">
                <div class="markdown step-task-card__body">
                    {!! parsedown_math($task->text) !!}
                </div>
            </div>
        </div>

        @if($nativeReview)
            @include('steps.partials.native-workspace', ['nativeTeacher' => true, 'nativeUser' => $student->id])
        @endif
        @forelse($activeSolutions as $solution)
            @include('steps.partials.review-attempt')
        @empty
            @unless($nativeReview)<div class="gc-empty-state"><h2 class="h6">Решений пока нет</h2><p class="mb-0">Когда ученик отправит работу, она появится здесь.</p></div>@endunless
        @endforelse
        @if($historySolutions->isNotEmpty())
            <details class="gc-disclosure gc-review-history" @if($historySolutions->contains(fn ($item) => (string) $item->id === (string) old('review_solution_id'))) open @endif>
                <summary>Предыдущие попытки <span class="gc-meta-label">{{ $historySolutions->count() }}</span></summary>
                <div class="gc-stack mt-3">@foreach($historySolutions as $solution) @include('steps.partials.review-attempt') @endforeach</div>
            </details>
        @endif
            </main>
        </div>
    </div>
@endsection
