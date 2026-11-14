            @php
                $solutionScoreBadgeClass = $solution->scoreBadgeClass();
                $solutionChecked = $solution->mark !== null;
                $useOldInput = (string) old('review_solution_id') === (string) $solution->id;
                $editingReview = $useOldInput || $solution->recheck_requested || (!empty($legacyReview) && $legacyReviewAttempt?->id === $solution->id);
                $solutionPendingReview = $pendingAttempts->contains('id', $solution->id);
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
            <div class="gc-card solution-review-card @if($solutionPendingReview) is-pending @else is-checked @endif" id="solution-{{ $solution->id }}" data-review-attempt>
                <div class="gc-section-header gc-section-header--between solution-review-card__header">
                    <div class="solution-review-card__title min-width-0">
                        <strong class="text-truncate">Решение #{{ $attemptNumbers[$solution->id] }}</strong>
                        <span class="text-muted small">{{ $solution->submitted?->format('d.m.Y H:i') ?? 'Черновик' }}</span>
                    </div>
                    <div class="solution-review-card__meta">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm gc-icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Действия с решением {{ $attemptNumbers[$solution->id] }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                @if($solutionPendingReview)<button type="submit" class="dropdown-item" form="skip-review-{{ $solution->id }}" data-confirm="Пропустить это решение в очереди проверки?">Пропустить решение</button>@endif
                                @if(!$taskAchievement)<button type="submit" class="dropdown-item" form="preview-achievement-{{ $solution->id }}">Создать достижение</button>
                                @else<a class="dropdown-item" href="{{ url('/insider/profile/'.$student->id.'#achievement-'.$taskAchievement->id) }}">Посмотреть достижение</a>@endif
                            </div>
                        </div>
                        @if($solution->recheck_requested)<span class="gc-task-state gc-task-state--pending">Перепроверка</span>@endif
                        @if ($solution->mark!=null)
                            @if($solution->hasActiveDeadlinePenalty())
                                <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{ $solution->mark }} / {{ $task->max_mark }} XP после штрафа</span>
                            @else
                                <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{ $solution->mark }} / {{ $task->max_mark }} XP</span>
                            @endif
                        @elseif($solution->review_skipped)
                            <span class="badge rounded-pill bg-body-tertiary text-muted border fw-medium">Пропущено</span>
                        @else
                            <span class="badge rounded-pill gc-badge--pending">На проверке</span>
                        @endif
                    </div>
                </div>
                <div class="solution-review-card__body">
                    <div class="solution-review-layout">
                        <div class="solution-review-layout__answer">
                            @if($geekPasteHasData)
                                <div class="solution-integrity-panel @if($solution->geekpaste_integrity_dismissed_at) is-dismissed @endif">
                                    <div class="solution-integrity-panel__head">
                                        <span class="solution-block__label mb-0">Анализ авторства · {{ $solution->geekpaste_integrity_dismissed_at ? 'предупреждение снято' : $geekPasteRiskLabel }}</span>
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
                                                <span>LLM {{ $solution->geekpaste_llm_probability }}%</span>
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
                                                <span>Совпадений: {{ $solution->geekpaste_similarity_matches_count }}</span>
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
                                    </div>
                                </div>
                            @endif

                            <div class="solution-block__label">Ответ ученика</div>
                            <div class="solution-answer {{ $task->is_code ? 'solution-answer--code' : 'solution-answer--text' }} mb-0 @if(trim($solution->text) === '') is-empty @endif" data-linkify>@if(trim($solution->text) === '')<span>Ответ пустой</span>@else{{ $solution->text }}@endif</div>

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
                                <div class="solution-feedback" @if($editingReview) hidden @endif id="solution-feedback-{{ $solution->id }}">
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
                                        <div class="markdown solution-feedback__content" data-linkify>{!! parsedown_math($solution->comment) !!}</div>
                                    @else
                                        <div class="small text-muted fst-italic">Комментарий не оставлен</div>
                                    @endif
                                </div>
                            @endif

                            @include('steps.partials.waive_deadline_penalty', ['solution' => $solution, 'course' => $course])

                            <form id="{{ $solutionGradeFormId }}"
                                  class="solution-grade-form @if($solutionChecked) solution-grade-form--recheck @if(!$editingReview) is-hidden @endif @endif"
                                  method="post"
                                  action="{{ url('insider/courses/'.$solution->course_id.'/solution/'.$solution->id) }}"
                                  @if($solutionChecked && !$editingReview) hidden @endif>
                                {{ csrf_field() }}
                                <input type="hidden" name="review_solution_id" value="{{ $solution->id }}">
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
                                        <textarea class="form-control rounded-3" id="comment-{{ $solution->id }}" name="comment" rows="2" placeholder="Что поправить">{{ $useOldInput ? old('comment') : ($solutionChecked ? '' : $solution->comment) }}</textarea>
                                    </div>
                                    <div class="solution-grade-field solution-grade-field--mark">
                                        <label for="mark-{{ $solution->id }}" class="form-label">XP <span class="text-muted fw-normal">из {{ $task->max_mark }}</span></label>
                                        <input type="number" class="form-control form-control-sm rounded-3" id="mark-{{ $solution->id }}" name="mark" min="0" max="{{ $task->max_mark }}" value="{{ $useOldInput ? old('mark') : ($solution->evaluated_mark ?? $solution->raw_mark ?? $solution->mark) }}" placeholder="0">
                                        @if ($useOldInput && $errors->has('mark'))
                                            <span class="text-danger small d-block mt-1"><strong>{{ $errors->first('mark') }}</strong></span>
                                        @endif
                                    </div>
                                    <div class="solution-grade-actions">
                                        <button type="submit" class="btn btn-primary btn-sm solution-grade-form__submit">
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
