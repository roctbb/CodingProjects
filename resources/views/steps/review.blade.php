@extends('layouts.left-menu')

@section('title')
    {{$student->name}}: {{$task->name}}
@endsection

@section('content')
    @php
        $pendingSolutionsCount = $solutions->filter(fn ($solution) => $solution->mark === null)->count();
        $checkedSolutionsCount = $solutions->count() - $pendingSolutionsCount;
        $isBlocked = ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin') && $task->isBlocked($student->id, $course->id);
    @endphp
    <div class="container-fluid px-0 solution-review-page">
        <div class="gc-card gc-page-header solution-review-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step->id.'#task'.$task->id) }}"><i class="icon ion-chevron-left"></i> К задаче</a>
                <h2 class="fw-bold lh-sm mb-1 text-truncate">Проверка решения</h2>
                <p class="mb-0 text-muted d-flex flex-wrap align-items-center gap-1">
                    <span class="text-truncate">{{ $student->name }}</span>
                    @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                    <span>· {{ $task->name }}</span>
                </p>
            </div>
            <div class="d-flex flex-column align-items-md-end gap-2">
                <div class="d-flex flex-wrap justify-content-md-end gap-2">
                    <span class="gc-summary-tile"><strong>{{ $solutions->count() }}</strong><span>решений</span></span>
                    <span class="gc-summary-tile"><strong>{{ $pendingSolutionsCount }}</strong><span>на проверке</span></span>
                    <span class="gc-summary-tile"><strong>{{ $checkedSolutionsCount }}</strong><span>проверено</span></span>
                </div>
                <div class="d-flex flex-wrap justify-content-md-end gap-2 solution-header-actions">
                    <a class="btn btn-sm gc-action-button solution-action" href="{{ url('/insider/courses/'.$course->id.'/assessments') }}"><i class="fas fa-table"></i> Журнал</a>
                    <a class="btn btn-sm gc-action-button solution-action" href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit') }}"><i class="fas fa-pen"></i> Редактировать</a>
                    @if ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin')
                        @if ($isBlocked)
                            <a class="btn btn-sm gc-action-button solution-action"
                               href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/unblock/'.$student->id) }}"
                               data-confirm="Разблокировать задачу для этого ученика?"><i class="fas fa-unlock"></i> Разблокировать</a>
                        @else
                            <a class="btn btn-sm gc-action-button solution-action solution-action--danger"
                               href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/block/'.$student->id) }}"
                               data-confirm="Заблокировать задачу для этого ученика? Все предыдущие баллы будут обнулены."><i class="fas fa-ban"></i> Заблокировать</a>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <div class="gc-card solution-task-card mb-3">
            <div class="gc-section-header gc-section-header--between">
                <div class="min-width-0">
                    <strong class="text-truncate d-block">{{ $task->name }}</strong>
                    <span class="small text-muted">Условие задачи</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge rounded-pill bg-body-tertiary text-nowrap">{{ $task->max_mark }} XP</span>
                    <button class="btn btn-sm gc-action-button solution-action solution-task-toggle"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#task-condition-{{ $task->id }}"
                            aria-expanded="true"
                            aria-controls="task-condition-{{ $task->id }}">
                        <i class="fas fa-chevron-up"></i>
                        <span class="solution-task-toggle__expanded">Свернуть</span>
                        <span class="solution-task-toggle__collapsed">Показать</span>
                    </button>
                </div>
            </div>
            <div class="collapse show" id="task-condition-{{ $task->id }}">
                <div class="markdown step-task-card__body">
                    {!! parsedown_math($task->text) !!}
                </div>
            </div>
        </div>

        @forelse ($solutions as $key => $solution)
            @php
                $solutionScoreBadgeClass = $solution->scoreBadgeClass();
            @endphp
            <div class="gc-card solution-review-card mb-3 @if($solution->mark === null) is-pending @else is-checked @endif" id="solution-{{ $solution->id }}">
                <div class="gc-section-header gc-section-header--between solution-review-card__header">
                    <div class="solution-review-card__title min-width-0">
                        <strong class="text-truncate">Решение #{{ $solutions->count() - $key }}</strong>
                        <span class="text-muted small">{{ $solution->submitted->format('d.m.Y H:i') }}</span>
                    </div>
                    @if ($solution->mark!=null)
                        @if($solution->hasActiveDeadlinePenalty())
                            <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{ $solution->mark }} / {{ $task->max_mark }} XP после штрафа</span>
                        @else
                            <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{ $solution->mark }} / {{ $task->max_mark }} XP</span>
                        @endif
                    @else
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">На проверке</span>
                    @endif
                </div>
                <div class="solution-review-card__body">
                    <div class="solution-review-layout">
                        <div class="solution-review-layout__answer">
                            <div class="solution-block__label">Ответ ученика</div>
                            <div class="solution-answer mb-0 @if(trim($solution->text) === '') is-empty @endif" data-linkify>
                                @if(trim($solution->text) === '')
                                    <span>Ответ пустой</span>
                                @else
                                    {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))!!}
                                @endif
                            </div>

                            @if ($solution->mark!=null)
                                <div class="solution-feedback">
                                    <div class="solution-block__label mb-2">Комментарий проверки</div>
                                    <div class="small text-muted mb-1">Проверено: {{ $solution->checked }}@if($solution->teacher), {{ $solution->teacher->name }}@endif</div>
                                    @if(trim((string) $solution->comment) !== '')
                                        <div class="small" data-linkify>{!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false))!!}</div>
                                    @else
                                        <div class="small text-muted fst-italic">Комментарий не оставлен</div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <form class="solution-grade-form @if($solution->mark !== null) is-checked @endif" method="post" action="{{ url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id) }}">
                            {{ csrf_field() }}
                            <div class="solution-grade-form__header">
                                <strong class="small text-uppercase text-muted">Оценка</strong>
                            </div>
                            <div class="solution-grade-grid">
                                <div class="solution-grade-field solution-grade-field--mark">
                                    <label for="mark-{{ $solution->id }}" class="form-label">XP <span class="text-muted fw-normal">из {{ $task->max_mark }}</span></label>
                                    <input type="number" class="form-control form-control-sm rounded-3" id="mark-{{ $solution->id }}" name="mark" min="0" max="{{ $task->max_mark }}" value="{{ old('mark', $solution->raw_mark ?: $solution->mark) }}" placeholder="0">
                                    @if ($errors->has('mark'))
                                        <span class="text-danger small d-block mt-1"><strong>{{ $errors->first('mark') }}</strong></span>
                                    @endif
                                </div>
                                <div class="solution-grade-field solution-grade-field--comment">
                                    <label for="comment-{{ $solution->id }}" class="form-label">Комментарий</label>
                                    <textarea class="form-control rounded-3" id="comment-{{ $solution->id }}" name="comment" rows="2" placeholder="Что поправить">{{ old('comment', $solution->comment) }}</textarea>
                                </div>
                                <div class="solution-grade-actions">
                                    <button type="submit" class="btn btn-success btn-sm rounded-3 fw-semibold solution-grade-form__submit">
                                        @if($solution->mark !== null)
                                            Обновить оценку
                                        @else
                                            Оценить
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="gc-empty-state">
                <div class="gc-empty-icon"><i class="fas fa-inbox"></i></div>
                <h5>Решений пока нет</h5>
                <p class="mx-auto mb-0">Когда ученик отправит работу, она появится здесь.</p>
            </div>
        @endforelse
    </div>
@endsection
