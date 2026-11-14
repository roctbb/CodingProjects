@if (!$quizer)
    @foreach ($tasks as $key => $task)
        <div class="tab-pane fade @if ($zero_theory && $loop->first) show active @endif"
             id="task{{$task->id}}"
             role="tabpanel" aria-labelledby="tasks-tab{{$task->id}}">
            @php
                $isInsider = \Request::is('insider/*');
                $nativeTaskChecker = $isInsider && \Illuminate\Support\Facades\Schema::hasTable('code_task_checkers') ? \App\CodeTaskChecker::find($task->id) : null;
                $isManager = $isInsider && ($course->teachers->contains($user) || $user->role=='admin');
                $deadline = $isInsider ? $task->getDeadline($course->id) : null;
                $latestUserSolution = $isInsider && Auth::check() ? $task->latestSolutionForUser(Auth::id()) : null;
                $hasUserSolution = $latestUserSolution !== null;
                $taskType = $task->is_code ? 'code' : ($task->is_quiz ? 'quiz' : 'text');
                $taskTypeLabel = ['code' => 'Код', 'quiz' => 'Квиз', 'text' => 'Ответ'][$taskType];
                $taskTypeIcon = ['code' => 'fas fa-code', 'quiz' => 'fas fa-question-circle', 'text' => 'fas fa-pen'][$taskType];
                $latestAiSummary = ($latestTaskAiSummaries ?? collect())->get($task->id);
                $latestAiPayload = $latestAiSummary ? ($latestAiSummary->payload ?? []) : [];
                $earnedAchievementId = ($earnedTaskAchievements ?? collect())->get($task->id);
                $taskStatusClass = '';
                if ($hasUserSolution) {
                    $taskStatusClass = is_null($latestUserSolution->mark)
                        ? 'is-review'
                        : ((int) $latestUserSolution->mark >= (int) $task->max_mark ? 'is-complete' : 'is-submitted');
                }
                $taskScoreBadgeClass = $hasUserSolution ? $latestUserSolution->scoreBadgeClass('bg-body') : 'bg-body';
            @endphp
            <div class="row">
                <div class="col">
                    @if ($task->is_star)
                        <div class="step-task-note step-task-note--optional" role="note">
                            <span class="text-warning-emphasis flex-shrink-0"><i class="fas fa-star"></i></span>
                            <div class="min-width-0">
                                <strong class="d-block">Необязательная задача</strong>
                                <span class="text-muted small">За ее решение вы получите дополнительный опыт.</span>
                            </div>
                        </div>
                    @endif
                    @if (!empty($latestAiPayload['summary']))
                        <section class="task-ai-summary-card mb-3" id="task-ai-summary-{{$task->id}}">
                            <div class="task-ai-summary__head">
                                <span class="task-ai-summary__icon"><i class="fas fa-newspaper"></i></span>
                                <div class="min-width-0">
                                    <h5 class="task-ai-summary__title mb-0">Пересказ решений</h5>
                                    <p class="task-ai-summary__meta mb-0">
                                        {{ $latestAiSummary->created_at->format('d.m.Y H:i') }}
                                        @if ($latestAiSummary->user)
                                            · {{ $latestAiSummary->user->name }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="task-ai-summary__text">
                                {!! nl2br(e($latestAiPayload['summary'])) !!}
                            </div>
                            @if (!empty($latestAiPayload['instruction']))
                                <div class="task-ai-summary__focus">
                                    <strong>Фокус:</strong> {{ $latestAiPayload['instruction'] }}
                                </div>
                            @endif
                        </section>
                    @endif
                    <div class="gc-section step-task-card step-task-card--{{$taskType}} @if ($task->is_star) is-optional @endif @if ($hasUserSolution) is-submitted @endif overflow-hidden">
                        @include('steps.partials.task_header')
                        <div class="markdown step-task-card__body">
                            {!! parsedown_math($task->text) !!}
                            @if ($isInsider)
                                @php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp
                                @if ($blocked)
                                    <div class="step-task-note step-task-note--danger bg-danger-subtle text-danger-emphasis mb-3" role="note">
                                        <span class="flex-shrink-0"><i class="fas fa-lock"></i></span>
                                        <div class="min-width-0">
                                            <strong class="d-block">Задача заблокирована</strong>
                                            <span class="small">Новые сдачи запрещены.</span>
                                        </div>
                                    </div>
                                @endif
                                @if ($task->is_code && !$nativeTaskChecker)
                                    <p class="alert alert-info">Для отправки решения нужно настроить способ сдачи и проверки этой задачи.</p>
                                @endif

                                @if ($user->role == 'student' and $task->solution!=null and $task->isFullDone(Auth::User()->id))
                                    <div class="step-author-solution mb-3">
                                        <h3 class="h6 mb-2">Авторское решение</h3>
                                        {!! parsedown_math($task->solution) !!}
                                    </div>
                                @endif
                                @if (($course->teachers->contains($user) || $user->role=='admin') and $task->solution != null)
                                    <p class="mb-3">
                                        <a class="btn btn-sm gc-action-button step-author-toggle" data-bs-toggle="collapse" href="#solution{{$task->id}}" role="button"
                                           aria-expanded="false"
                                           aria-controls="collapseExample">
                                            Авторское решение
                                        </a>
                                    </p>
                                    <div class="collapse mb-3" id="solution{{$task->id}}">
                                        <div class="step-author-solution">
                                            {!! parsedown_math($task->solution) !!}
                                        </div>
                                    </div>
                                @endif
                                @if ($task->is_quiz)
                                    @if (!$blocked)
                                        <form action="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution')}}"
                                              method="POST"
                                              class="step-quiz-answer-form"
                                              data-check-task data-task-id="{{$task->id}}">
                                            {{ csrf_field() }}
                                            <label for="text{{$task->id}}" class="form-label fw-medium mb-0">Ответ</label>
                                            <input type="text" name="text" class="form-control form-control-sm rounded-3"
                                                   id="text{{$task->id}}"/>
                                            <button type="submit" class="btn btn-success btn-sm rounded-3">Отправить
                                            </button>
                                        </form>
                                        @if ($errors->has('text'))
                                            <span class="text-danger small d-block mb-3"><strong>{{ $errors->first('text') }}</strong></span>
                                        @endif
                                    @endif
                                @endif
                                <div class="step-task-status-row {{ $blocked ? 'is-blocked' : $taskStatusClass }} @if (!$blocked && !($task->is_quiz && $hasUserSolution)) d-none @endif"
                                     data-task-status-row>
                                    @if ($blocked)
                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-medium" id="TSK_{{$task->id}}">0 XP</span>
                                        <span class="small text-muted" id="TSK_COM_{{$task->id}}">Задача заблокирована</span>
                                    @elseif ($task->is_quiz && $hasUserSolution)
                                        <span class="badge rounded-pill {{ $taskScoreBadgeClass }} step-task-score"
                                              id="TSK_{{$task->id}}">{{$latestUserSolution->mark}} XP</span>
                                        <span class="small text-muted" id="TSK_COM_{{$task->id}}">{{$latestUserSolution->comment}}</span>
                                    @else
                                        <span class="badge rounded-pill bg-body step-task-score" id="TSK_{{$task->id}}"></span>
                                        <span class="small text-muted" id="TSK_COM_{{$task->id}}"></span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                    @if ($isManager)
                        <div class="modal fade" id="task-ai-summary-modal-{{$task->id}}" tabindex="-1" aria-labelledby="task-ai-summary-title-{{$task->id}}" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content task-ai-summary-modal">
                                    <div class="modal-header">
                                        <div class="task-ai-summary__head min-width-0">
                                            <span class="task-ai-summary__icon"><i class="fas fa-newspaper"></i></span>
                                            <div class="min-width-0">
                                                <h5 class="task-ai-summary__title mb-0" id="task-ai-summary-title-{{$task->id}}">Пересказ решений</h5>
                                                <p class="task-ai-summary__meta mb-0 text-truncate">{{ $task->name }}</p>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                                    </div>
                                    <div class="modal-body task-ai-summary-modal__body">
                                        @if ($latestAiSummary)
                                            <p class="task-ai-summary__meta mb-0">
                                                Последний пересказ: {{ $latestAiSummary->created_at->format('d.m.Y H:i') }}.
                                                Текст показан над задачей.
                                            </p>
                                        @else
                                            <p class="task-ai-summary__meta mb-0">Пересказ еще не генерировался.</p>
                                        @endif

                                        <form method="POST"
                                              action="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/ai-summary') }}"
                                              class="task-ai-summary__form">
                                            {{ csrf_field() }}
                                            <div class="task-ai-summary__field">
                                                <label for="summary-instruction-{{$task->id}}" class="form-label">На что обратить внимание</label>
                                                <textarea class="form-control"
                                                          id="summary-instruction-{{$task->id}}"
                                                          name="summary_instruction"
                                                          rows="3"
                                                          maxlength="1000"
                                                          placeholder="Смысловая часть, технические приемы, необычные сюжеты, частые ошибки">{{ old('summary_instruction') }}</textarea>
                                            </div>
                                            <div class="task-ai-summary__actions">
                                                <button type="submit"
                                                        class="btn btn-primary btn-sm rounded-3"
                                                        data-confirm="Сгенерировать AI-новость по решениям задачи и опубликовать ее в пульсе?">
                                                    <i class="fas fa-magic me-1"></i>Сгенерировать
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @if (\Request::is('insider/*'))

                @if (!$task->is_quiz)
                    @if ($course->teachers->contains($user) || $user->role == 'admin')
                        @php
                            $stepProgressStudents = $course->relationLoaded('statsStudents') ? $course->statsStudents : $course->students;
                            $ownTabActive = $errors->has('text');
                        @endphp
                        <div class="gc-task-workspaces mt-3">
                            <div class="nav gc-segmented-tabs" role="tablist" aria-label="Работа с задачей">
                                <button class="nav-link @if(!$ownTabActive) active @endif" id="student-solutions-tab-{{ $task->id }}" type="button" role="tab" data-no-tab-history data-bs-toggle="tab" data-bs-target="#student-solutions-{{ $task->id }}" data-execution-tab-target="#execution-workspace-{{ $task->id }}" aria-controls="student-solutions-{{ $task->id }}" aria-selected="{{ $ownTabActive ? 'false' : 'true' }}">Решения учеников</button>
                                <button class="nav-link @if($ownTabActive) active @endif" id="own-solution-tab-{{ $task->id }}" type="button" role="tab" data-no-tab-history data-bs-toggle="tab" data-bs-target="#own-solution-{{ $task->id }}" data-execution-tab-target="#execution-own-workspace-{{ $task->id }}" aria-controls="own-solution-{{ $task->id }}" aria-selected="{{ $ownTabActive ? 'true' : 'false' }}">Моё решение</button>
                            </div>
                            <div class="tab-content">
                            <div class="tab-pane fade @if(!$ownTabActive) show active @endif" id="student-solutions-{{ $task->id }}" role="tabpanel" aria-labelledby="student-solutions-tab-{{ $task->id }}" tabindex="0">
                        <div class="row my-3">
                            <div class="col">
                                <div class="gc-section step-progress-card overflow-hidden" data-execution-review-navigation>
                                    <div data-execution-student-list>
                                    <div class="gc-section-header gc-section-header--between">
                                        <div>
                                            <h6 class="mb-0">Прогресс учеников</h6>
                                            <p class="text-muted small mb-0">Выберите ученика, чтобы увидеть его сдачи.</p>
                                        </div>
                                        <div class="input-group input-group-sm gc-search-box step-progress-search">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="search"
                                                   class="form-control"
                                                   placeholder="Найти ученика"
                                                   aria-label="Найти ученика"
                                                   data-step-progress-search
                                                   data-step-progress-grid="#step-progress-grid-{{ $task->id }}">
                                            <span class="input-group-text gc-search-box__count" data-step-progress-count>{{ $stepProgressStudents->count() }} из {{ $stepProgressStudents->count() }}</span>
                                        </div>
                                    </div>
                                    <div class="step-progress-grid-wrap">
                                        <div class="step-progress-grid" id="step-progress-grid-{{ $task->id }}">
                                        @foreach($stepProgressStudents as $student)
                                            @php
                                                $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                return $value->user_id == $student->id && $value->submitted !== null;
                                                });
                                                $bestSolution = \App\Solution::bestScoredIn($filtered);
                                                $latestSolution = $filtered->sortByDesc('submitted')->first();
                                                $nativeProgress = ($nativeTaskProgress ?? collect())->get($task->id, collect())->get($student->id);
                                                $latestSubmittedAt = $latestSolution?->submitted;
                                                if ($nativeProgress && (!$latestSubmittedAt || $nativeProgress['latest_at']?->gt($latestSubmittedAt))) $latestSubmittedAt = $nativeProgress['latest_at'];
                                                $blocked = $task->isBlocked($student->id, $course->id);
                                                if ($blocked) {
                                                    $mark = 0;
                                                    $need_check = false;
                                                    $class = 'bg-danger-subtle text-danger border border-danger-subtle fw-medium';
                                                    $stateClass = 'is-blocked';
                                                    $stateLabel = 'Заблокировано';
                                                } else {
                                                    $mark = $bestSolution ? $bestSolution->mark : 0;
                                                    $need_check = false;
                                                    $recheckRequested = $filtered->filter(fn ($solution) => $solution->recheck_requested)->isNotEmpty();
                                                    if ($filtered->filter(fn ($solution) => $solution->submitted && $solution->mark === null && !$solution->review_skipped)->isNotEmpty())
                                                    {
                                                    $need_check = true;
                                                    }
                                                    $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary text-muted fw-medium') : 'bg-body-tertiary text-muted fw-medium';
                                                    $stateClass = ($need_check || $recheckRequested) ? 'is-pending' : ($bestSolution ? 'is-checked' : 'is-empty');
                                                    $stateLabel = $recheckRequested ? 'На перепроверку' : ($need_check ? 'На проверке' : ($bestSolution ? 'Проверено' : 'Нет решений'));
                                                    if ($mark >= $task->max_mark * 0.5)
                                                    {
                                                    $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary text-muted fw-medium') : 'bg-body-tertiary text-muted fw-medium';
                                                    }
                                                    if ($mark >= $task->max_mark * 0.7)
                                                    {
                                                    $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary text-muted fw-medium') : 'bg-body-tertiary text-muted fw-medium';
                                                    }
                                                    if ($need_check)
                                                    {
                                                    $class = 'gc-badge--pending';
                                                    }
                                                    if ($nativeProgress['pending'] ?? false) {
                                                        $need_check = true;
                                                        $class = 'gc-badge--pending';
                                                        $stateClass = 'is-pending';
                                                        $stateLabel = 'Проверяется';
                                                    } elseif (!$bestSolution && ($nativeProgress['error'] ?? false)) {
                                                        $stateClass = 'is-blocked';
                                                        $stateLabel = 'Ошибка проверки';
                                                    } elseif (!$bestSolution && !empty($nativeProgress['attempts'])) {
                                                        $stateLabel = 'Решение отправлено';
                                                    }
                                                }
                                                $maxMark = max(1, (int) $task->max_mark);
                                                $progressPercent = min(100, max(0, round(((int) $mark / $maxMark) * 100)));
                                                $solutionsCount = $filtered->count() + ($nativeProgress['attempts'] ?? 0);
                                                $searchTitle = trim($student->name . ' ' . $stateLabel . ' ' . $mark . ' ' . $task->max_mark);
                                            @endphp
                                            <a class="step-progress-cardlet {{ $stateClass }}"
                                               href="{{url('/insider/courses/'.$course->id.'/steps/'.$step->id).'?student='.$student->id.'&task='.$task->id.'#task'.$task->id}}"
                                                   data-execution-student="#execution-workspace-{{ $task->id }}"
                                                   data-student-id="{{ $student->id }}" data-student-name="{{ $student->name }}"
                                               data-step-progress-card
                                               data-step-progress-text="{{ $searchTitle }}">
                                                <span class="step-progress-cardlet__head">
                                                    <span class="step-progress-student">
                                                        <span>{{$student->name}}</span>
                                                    </span>
                                                    <span class="badge rounded-pill step-progress-mark {{$class}}">{{$mark}} / {{ $task->max_mark }}</span>
                                                </span>
                                                <span class="step-progress-cardlet__bar" aria-hidden="true">
                                                    <span style="width: {{ $progressPercent }}%"></span>
                                                </span>
                                                <span class="step-progress-cardlet__meta">
                                                    <span>{{ $stateLabel }}</span>
                                                    <span>
                                                        @if($solutionsCount > 0)
                                                            {{ $solutionsCount }} {{ trans_choice('сдача|сдачи|сдач', $solutionsCount) }}
                                                            @if($latestSubmittedAt)
                                                                · {{ $latestSubmittedAt->format('d.m H:i') }}
                                                            @endif
                                                        @else
                                                            перейти к ученику
                                                        @endif
                                                    </span>
                                                </span>
                                            </a>
                                        @endforeach
                                        </div>
                                        <div class="step-progress-empty text-muted small text-center d-none" data-step-progress-empty>Ничего не найдено</div>
                                    </div>
                                    </div>
                                    @if($nativeTaskChecker)<p class="gc-help-text px-3 mb-2" role="status" data-execution-status-for="execution-workspace-{{ $task->id }}" hidden></p>@endif
                                        @include('steps.partials.native-workspace', ['nativeTeacher' => true])
                                </div>
                            </div>
                        </div>
                            </div>
                            <div class="tab-pane fade @if($ownTabActive) show active @endif" id="own-solution-{{ $task->id }}" role="tabpanel" aria-labelledby="own-solution-tab-{{ $task->id }}" tabindex="0">
                                @if($nativeTaskChecker)
                                    @include('steps.partials.native-workspace', ['nativeTeacher' => true, 'nativeUser' => Auth::id(), 'nativeWorkspaceId' => 'execution-own-workspace-'.$task->id, 'nativeTabWorkspace' => true])
                                @else
                                    @if(!$task->is_code) @include('steps.partials.task_solution_form') @endif
                                    @include('steps.partials.native-workspace', ['nativeTeacher' => true, 'nativeUser' => Auth::id(), 'nativeWorkspaceId' => 'execution-own-workspace-'.$task->id, 'nativeTabWorkspace' => true, 'nativeHistoryOnly' => true])
                                @endif
                            </div>
                            </div>
                        </div>
                    @endif
                    @if(!$nativeTaskChecker && !$isManager) @include('steps.partials.native-workspace', ['nativeTeacher' => false, 'nativeHistoryOnly' => true]) @endif
                @endif

                @if($isInsider && $nativeTaskChecker && !$isManager)
                    @include('steps.partials.native-workspace', ['nativeTeacher' => $isManager])
                @endif
                @if (!$task->is_quiz && !$task->is_code && !$nativeTaskChecker)
                    @if(!$isManager)
                        @include('steps.partials.task_solution_form')
                    @endif
                @endif
            @endif
        </div>
    @endforeach
    <div data-step-content-tabs data-zero-theory="{{ $zero_theory ? 'true' : 'false' }}" hidden></div>
@endif
