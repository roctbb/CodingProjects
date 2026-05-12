@extends('layouts.left-menu')

@section('title')
    {{$student->name}}: {{$task->name}}
@endsection

@section('head')
    @include('layouts.partials.mathjax')
@endsection

@section('content')
    @php
        $pendingSolutionsCount = $solutions->filter(fn ($solution) => $solution->isPendingReview())->count();
        $checkedSolutionsCount = $solutions->count() - $pendingSolutionsCount;
        $isBlocked = ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin') && $task->isBlocked($student->id, $course->id);
    @endphp
    <div class="container-fluid px-0 solution-review-page">
        <div class="gc-card gc-page-header solution-review-header mb-3">
            <div class="solution-review-header__main min-width-0">
                <a class="solution-review-header__back"
                   href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step->id.'#task'.$task->id) }}"
                   title="К задаче"
                   aria-label="К задаче">
                    <i class="icon ion-chevron-left"></i>
                </a>
                <div class="solution-review-header__copy min-width-0">
                    <h2 class="solution-review-header__title">Проверка решения</h2>
                    <p class="solution-review-header__meta mt-1">
                        <span class="text-truncate">{{ $student->name }}</span>
                        @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                        <span>· {{ $task->name }}</span>
                    </p>
                </div>
            </div>
            <div class="solution-review-header__actions solution-header-actions">
                <span class="solution-review-header__status @if($pendingSolutionsCount) has-pending @endif">
                    Проверено {{ $checkedSolutionsCount }} / {{ $solutions->count() }} решений
                </span>
                <a class="btn btn-sm gc-action-button solution-action solution-action--icon"
                   href="{{ url('/insider/courses/'.$course->id.'/assessments') }}"
                   title="Журнал"
                   aria-label="Журнал">
                    <i class="fas fa-table"></i>
                </a>
                <a class="btn btn-sm gc-action-button solution-action solution-action--icon"
                   href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit') }}"
                   title="Редактировать задачу"
                   aria-label="Редактировать задачу">
                    <i class="fas fa-pen"></i>
                </a>
                @if ($course->teachers->contains(Auth::user()) || Auth::user()->role=='admin')
                    @if($pendingSolutionsCount)
                        <form method="post" action="{{ url('insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id.'/skip-review') }}" onsubmit="return confirm('Пропустить все непроверенные решения этого ученика по задаче?');">
                            {{ csrf_field() }}
                            <button type="submit"
                                    class="btn btn-sm gc-action-button solution-action solution-action--icon"
                                    title="Пропустить все непроверенные решения"
                                    aria-label="Пропустить все непроверенные решения">
                                <i class="fas fa-check-double"></i>
                            </button>
                        </form>
                    @endif
                    @if ($isBlocked)
                        <a class="btn btn-sm gc-action-button solution-action solution-action--icon"
                           href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/unblock/'.$student->id) }}"
                           data-confirm="Разблокировать задачу для этого ученика?"
                           title="Разблокировать задачу"
                           aria-label="Разблокировать задачу"><i class="fas fa-unlock"></i></a>
                    @else
                        <a class="btn btn-sm gc-action-button solution-action solution-action--icon solution-action--danger"
                           href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/block/'.$student->id) }}"
                           data-confirm="Заблокировать задачу для этого ученика? Все предыдущие баллы будут обнулены."
                           title="Заблокировать задачу"
                           aria-label="Заблокировать задачу"><i class="fas fa-ban"></i></a>
                    @endif
                @endif
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
                $solutionChecked = $solution->mark !== null;
                $solutionPendingReview = $solution->isPendingReview();
                $solutionGradeFormId = ($solutionChecked ? 'solution-recheck-form-' : 'solution-grade-form-') . $solution->id;
                $geekPasteHasData = $solution->hasGeekPasteIntegrityData();
                $geekPasteRiskLevel = $solution->geekPasteIntegrityRiskLevel();
                $geekPasteRiskTone = [
                    'high' => 'report-risk-badge--high',
                    'medium' => 'report-risk-badge--medium',
                    'low' => 'report-risk-badge--low',
                    'none' => 'report-risk-badge--none',
                ][$geekPasteRiskLevel] ?? 'report-risk-badge--none';
                $geekPasteRiskLabel = [
                    'high' => 'высокий риск',
                    'medium' => 'средний риск',
                    'low' => 'низкий риск',
                    'none' => 'нет активного риска',
                ][$geekPasteRiskLevel] ?? $geekPasteRiskLevel;
                $geekPasteConfidenceLabel = [
                    'high' => 'высокая',
                    'medium' => 'средняя',
                    'low' => 'низкая',
                ][$solution->geekpaste_ai_confidence] ?? $solution->geekpaste_ai_confidence;
                $geekPasteSuspicionKind = $solution->geekPasteIntegritySuspicionKind();
                $geekPasteRiskIcon = $geekPasteSuspicionKind === 'ai' ? 'fa-robot' : ($geekPasteSuspicionKind === 'similarity' ? 'fa-exclamation-triangle' : 'fa-shield-alt');
            @endphp
            <div class="gc-card solution-review-card mb-3 @if($solutionPendingReview) is-pending @else is-checked @endif" id="solution-{{ $solution->id }}">
                <div class="gc-section-header gc-section-header--between solution-review-card__header">
                    <div class="solution-review-card__title min-width-0">
                        <strong class="text-truncate">Решение #{{ $loop->remaining + 1 }}</strong>
                        <span class="text-muted small">{{ $solution->submitted->format('d.m.Y H:i') }}</span>
                    </div>
                    <div class="solution-review-card__meta">
                        <div class="solution-review-card__quick-actions">
                            @if($solutionPendingReview)
                                <button type="submit"
                                        class="solution-review-card__icon-action"
                                        form="skip-review-{{ $solution->id }}"
                                        title="Пропустить решение"
                                        aria-label="Пропустить решение"
                                        onclick="return confirm('Пропустить это решение в очереди проверки?');">
                                    <i class="fas fa-check"></i>
                                </button>
                            @endif
                            @if(!$taskAchievement)
                                <button type="submit"
                                        class="solution-review-card__icon-action solution-review-card__icon-action--achievement"
                                        form="preview-achievement-{{ $solution->id }}"
                                        title="Сгенерировать достижение"
                                        aria-label="Сгенерировать достижение">
                                    <i class="fas fa-award"></i>
                                </button>
                            @else
                                <a class="solution-review-card__icon-action solution-review-card__icon-action--achievement is-issued"
                                   href="{{ url('/insider/profile/'.$student->id.'#achievement-'.$taskAchievement->id) }}"
                                   title="Достижение выдано"
                                   aria-label="Достижение выдано">
                                    <i class="fas fa-award"></i>
                                </a>
                            @endif
                        </div>
                        @if ($solution->mark!=null)
                            @if($solution->hasActiveDeadlinePenalty())
                                <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{ $solution->mark }} / {{ $task->max_mark }} XP после штрафа</span>
                            @else
                                <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{ $solution->mark }} / {{ $task->max_mark }} XP</span>
                            @endif
                        @elseif($solution->review_skipped)
                            <span class="badge rounded-pill bg-body-tertiary text-muted border fw-semibold">Пропущено</span>
                        @else
                            <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">На проверке</span>
                        @endif
                    </div>
                </div>
                <div class="solution-review-card__body">
                    <div class="solution-review-layout">
                        <div class="solution-review-layout__answer">
                            @if($geekPasteHasData)
                                <div class="solution-integrity-panel @if($solution->geekpaste_integrity_dismissed_at) is-dismissed @endif">
                                    <div class="solution-integrity-panel__head">
                                        <span class="solution-block__label mb-0">GeekPaste</span>
                                        <span class="solution-integrity-icons">
                                            <span class="report-integrity-icon {{ $geekPasteRiskTone }}"
                                                  title="{{ $solution->geekpaste_integrity_dismissed_at ? 'Предупреждение снято' : 'Риск: '.$geekPasteRiskLabel }}">
                                                <i class="fas {{ $solution->geekpaste_integrity_dismissed_at ? 'fa-eye-slash' : $geekPasteRiskIcon }}"></i>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="solution-integrity-metrics">
                                        @if($solution->geekpaste_llm_probability !== null)
                                            <span class="report-integrity-metric" title="Вероятность LLM">
                                                <i class="fas fa-robot"></i>
                                                <span>{{ $solution->geekpaste_llm_probability }}%</span>
                                            </span>
                                        @endif
                                        @if($solution->geekpaste_similarity_max_percent !== null)
                                            <span class="report-integrity-metric" title="Максимальная схожесть">
                                                <i class="fas fa-copy"></i>
                                                <span>{{ $solution->geekpaste_similarity_max_percent }}%</span>
                                            </span>
                                        @endif
                                        @if((int) $solution->geekpaste_similarity_matches_count > 0)
                                            <span class="report-integrity-metric" title="Найдено похожих сдач">
                                                <i class="fas fa-link"></i>
                                                <span>{{ $solution->geekpaste_similarity_matches_count }}</span>
                                            </span>
                                        @endif
                                        @if($solution->geekpaste_ai_confidence)
                                            <span class="report-integrity-metric report-risk-badge--ai" title="AI confidence">
                                                <i class="fas fa-robot"></i>
                                                <span>{{ $geekPasteConfidenceLabel }}</span>
                                            </span>
                                        @elseif($solution->geekpaste_ai_warning)
                                            <span class="report-integrity-metric report-risk-badge--ai" title="AI предупреждение">
                                                <i class="fas fa-robot"></i>
                                                <span>AI</span>
                                            </span>
                                        @endif
                                        @if($solution->geekpaste_integrity_dismissed_at)
                                            <span class="report-integrity-metric is-muted" title="Снято {{ $solution->geekpaste_integrity_dismissed_at->format('d.m.Y H:i') }}">
                                                <i class="fas fa-eye-slash"></i>
                                                <span>снято</span>
                                            </span>
                                        @elseif($solution->geekpaste_integrity_synced_at)
                                            <span class="report-integrity-metric is-muted" title="Синхронизировано {{ $solution->geekpaste_integrity_synced_at->format('d.m.Y H:i') }}">
                                                <i class="fas fa-clock"></i>
                                                <span>{{ $solution->geekpaste_integrity_synced_at->format('d.m') }}</span>
                                            </span>
                                        @endif
                                        @if($solution->geekpaste_code_id)
                                            <span class="report-integrity-metric is-muted" title="GeekPaste ID">
                                                <i class="fas fa-code"></i>
                                                <span>{{ $solution->geekpaste_code_id }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <div class="solution-block__label">Ответ ученика</div>
                            <div class="solution-answer mb-0 @if(trim($solution->text) === '') is-empty @endif" data-linkify>
                                @if(trim($solution->text) === '')
                                    <span>Ответ пустой</span>
                                @else
                                    {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))!!}
                                @endif
                            </div>

                            @if($solution->recheck_requested)
                                <div class="solution-recheck-note">
                                    <div class="solution-block__label mb-2">Запрос на перепроверку</div>
                                    @if(trim((string) $solution->recheck_comment) !== '')
                                        <div class="small" data-linkify>{!! nl2br(e($solution->recheck_comment)) !!}</div>
                                    @else
                                        <div class="small text-muted fst-italic">Ученик не оставил комментарий</div>
                                    @endif
                                </div>
                            @endif

                            @if ($solutionChecked)
                                <div class="solution-feedback" id="solution-feedback-{{ $solution->id }}">
                                    <div class="solution-feedback__header">
                                        <div class="min-width-0">
                                            <div class="solution-block__label mb-2">Комментарий проверки</div>
                                            <div class="small text-muted mb-1">Проверено: {{ $solution->checked }}@if($solution->teacher), {{ $solution->teacher->name }}@endif</div>
                                        </div>
                                        <button type="button"
                                                class="btn btn-sm gc-action-button solution-feedback__recheck"
                                                data-solution-recheck-toggle
                                                data-solution-feedback-id="solution-feedback-{{ $solution->id }}"
                                                data-solution-form-id="{{ $solutionGradeFormId }}">
                                            <i class="fas fa-redo-alt"></i>
                                            <span>Перепроверить</span>
                                        </button>
                                    </div>
                                    @if(trim((string) $solution->comment) !== '')
                                        <div class="small" data-linkify>{!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false))!!}</div>
                                    @else
                                        <div class="small text-muted fst-italic">Комментарий не оставлен</div>
                                    @endif
                                </div>
                            @endif

                            <form id="{{ $solutionGradeFormId }}"
                                  class="solution-grade-form @if($solutionChecked) solution-grade-form--recheck is-hidden @endif"
                                  method="post"
                                  action="{{ url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id) }}"
                                  @if($solutionChecked) hidden @endif>
                                {{ csrf_field() }}
                                <div class="solution-grade-form__header">
                                    <strong class="small text-uppercase text-muted">{{ $solutionChecked ? 'Перепроверка' : 'Оценка' }}</strong>
                                    @if($solutionChecked)
                                        <button type="button"
                                                class="btn btn-link btn-sm text-muted p-0 solution-grade-form__cancel"
                                                data-solution-recheck-cancel
                                                data-solution-feedback-id="solution-feedback-{{ $solution->id }}"
                                                data-solution-form-id="{{ $solutionGradeFormId }}">
                                            Отмена
                                        </button>
                                    @endif
                                </div>
                                <div class="solution-grade-grid">
                                    <div class="solution-grade-field solution-grade-field--comment">
                                        <label for="comment-{{ $solution->id }}" class="form-label">Комментарий</label>
                                        <textarea class="form-control rounded-3" id="comment-{{ $solution->id }}" name="comment" rows="2" placeholder="Что поправить">{{ old('comment', $solution->comment) }}</textarea>
                                    </div>
                                    <div class="solution-grade-field solution-grade-field--mark">
                                        <label for="mark-{{ $solution->id }}" class="form-label">XP <span class="text-muted fw-normal">из {{ $task->max_mark }}</span></label>
                                        <input type="number" class="form-control form-control-sm rounded-3" id="mark-{{ $solution->id }}" name="mark" min="0" max="{{ $task->max_mark }}" value="{{ old('mark', $solution->raw_mark ?: $solution->mark) }}" placeholder="0">
                                        @if ($errors->has('mark'))
                                            <span class="text-danger small d-block mt-1"><strong>{{ $errors->first('mark') }}</strong></span>
                                        @endif
                                    </div>
                                    <div class="solution-grade-actions">
                                        <button type="submit" class="btn btn-success btn-sm rounded-3 fw-semibold solution-grade-form__submit">
                                            {{ $solutionChecked ? 'Обновить оценку' : 'Оценить' }}
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        @if($solutionPendingReview)
                            <form id="skip-review-{{ $solution->id }}" method="post" action="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/skip-review') }}">
                                {{ csrf_field() }}
                            </form>
                        @endif
                        @if(!$taskAchievement)
                            <form id="preview-achievement-{{ $solution->id }}"
                                  method="post"
                                  action="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/achievement-preview') }}"
                                  data-confirm="Вы точно хотите сгенерировать достижение для этого решения?"
                                  data-fullscreen-loading
                                  data-loading-message="Генерирую варианты достижения">
                                {{ csrf_field() }}
                            </form>
                        @endif
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
