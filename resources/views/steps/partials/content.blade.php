@if (!$quizer)
    @php
        $taskMaterials = collect();
        if (\Request::is('insider/*') && \Illuminate\Support\Facades\Schema::hasTable('code_task_files')) {
            $materialsManager = $course->teachers->contains($user) || $user->role === 'admin';
            $taskMaterials = \App\CodeTaskFile::whereIn('task_id', $tasks->pluck('id'))
                ->when(!$materialsManager, fn ($query) => $query->where('is_public', true))
                ->orderBy('path')->get(['id', 'task_id', 'path', 'kind', 'is_public', 'read_only'])->groupBy('task_id');
        }
    @endphp
    @foreach ($tasks as $key => $task)
        @continue(!empty($studentAttempt) && $task->id !== $attemptTask->id)
        <div @if(empty($studentAttempt))
                 class="tab-pane fade @if ($initialTaskId == $task->id) show active @endif"
                 id="task{{$task->id}}" role="tabpanel" aria-labelledby="tasks-tab{{$task->id}}"
             @endif>
            @php
                $isInsider = \Request::is('insider/*');
                $nativeTaskChecker = $isInsider ? $task->workbookChecker : null;
                $isManager = $isInsider && ($course->teachers->contains($user) || $user->role=='admin');
                $deadline = $isInsider ? $task->getDeadline($course->id, $isManager ? null : $user->id) : null;
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
                $visibleMaterials = $taskMaterials->get($task->id, collect());
                $editorStarter = null;
                if (!$task->is_quiz && in_array('code', $nativeTaskChecker?->config['submission_modes'] ?? [], true)
                    && $visibleMaterials->where('kind', 'starter')->count() === 1) {
                    $file = $visibleMaterials->firstWhere('kind', 'starter');
                    if (!$file->read_only && strtolower(pathinfo($file->path, PATHINFO_EXTENSION)) !== 'zip') {
                        $editorStarter = $file;
                        $visibleMaterials = $visibleMaterials->reject(fn ($item) => $item->id === $file->id);
                    }
                }
                $studentSheet = $isInsider && !$isManager && !$task->is_quiz;
                $teacherSheet = $isManager && !$task->is_quiz;
                $inlineCondition = $teacherSheet || ($studentSheet && empty($studentAttempt));
                $ownWorkspaceActive = $teacherSheet && $errors->has('text');
            @endphp
            <div class="gc-task-sheet @if($isInsider && $task->is_quiz) gc-section step-task-card step-task-card--quiz gc-quiz-card @elseif($studentSheet) gc-chat-sheet gc-task-sheet--student @elseif($teacherSheet) gc-chat-sheet gc-task-sheet--teacher @endif">
            @if($teacherSheet || $studentSheet)
                @include('steps.partials.task_header', ['worksheet' => true])
            @endif
            @if($isInsider && $task->is_quiz)
                @include('steps.partials.task_header')
            @elseif($inlineCondition)
                <section class="gc-task-condition" aria-label="Условие задачи" data-task-condition="{{ $task->id }}">
            @else
            <details class="gc-task-condition-disclosure" data-task-condition="{{ $task->id }}"
                     @if($latestUserSolution?->submitted || ($submittedCodeTasks ?? collect())->has($task->id)) data-submitted @endif
                     @if($studentSheet || ($attemptTask ?? null)?->id !== $task->id) open @endif>
            <summary>
                @if($teacherSheet || $studentSheet || !empty($studentAttempt))
                    <span>Условие</span>
                @else
                <span class="gc-condition-label">Условие задачи · {{ $task->name }}</span>
                <div class="gc-task-intro {{ $isManager ? 'is-teacher' : 'is-student' }}">
                @include('steps.partials.task_header')
            </div>
                @endif
                <i class="fas fa-chevron-down" aria-hidden="true"></i></summary>
            @endif
                <div class="gc-task-context">
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
                    @if($visibleMaterials->isNotEmpty())
                        <section class="gc-task-materials" aria-labelledby="task-materials-title-{{ $task->id }}">
                            <h3 id="task-materials-title-{{ $task->id }}" @if($studentSheet && $visibleMaterials->count() === 1) class="visually-hidden" @endif>Материалы задания</h3>
                            <ul class="gc-task-materials__list list-unstyled mb-0">
                                @foreach($visibleMaterials as $material)
                                    <li><a class="gc-task-materials__file" href="{{ route('code-task-files.download', ['course' => $course->id, 'task' => $task->id, 'attachment' => $material->id]) }}" target="_blank" rel="noopener">
                                        <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                                        <span>{{ $material->path }}</span>
                                        @if($material->kind === 'starter')<small>Шаблон кода</small>@elseif($material->kind === 'resource')<small>Данные для программы</small>@endif
                                        @if($material->read_only)<small>Только чтение</small>@endif
                                        @if(!$material->is_public)<small><i class="fas fa-lock" aria-hidden="true"></i> Для преподавателя</small>@endif
                                    </a></li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                    <div class="gc-task-condition-content">
                        <div class="markdown step-task-card__body">
                            {!! parsedown_math($task->text) !!}
                            @if ($isInsider)
                                @php $blocked = $task->isBlocked(Auth::User()->id, $course->id); @endphp
                                @if ($blocked && !$task->is_quiz)
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
                                @if(!$task->is_quiz)
                                    <div class="step-task-status-row {{ $blocked ? 'is-blocked' : $taskStatusClass }} @if(!$blocked) d-none @endif" data-task-status-row>
                                        <span class="badge rounded-pill {{ $blocked ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-body step-task-score' }}"
                                              id="TSK_{{$task->id}}">{{ $blocked ? '0 XP' : '' }}</span>
                                        <span class="small text-muted" id="TSK_COM_{{$task->id}}">{{ $blocked ? 'Задача заблокирована' : '' }}</span>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                    @if($studentSheet && !$blocked && ($nativeTaskChecker ? empty($nativeTaskChecker->config['requires_conversion']) : !$task->is_code))
                        <div data-task-submit-slot>
                            <div class="gc-task-submit-action">
                                <button type="button" class="btn btn-primary" data-task-compose="execution-workspace-{{ $task->id }}">Сдать решение</button>
                            </div>
                        </div>
                    @endif
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
            @unless($isInsider && $task->is_quiz)
                @if($inlineCondition)</section>@else</details>@endif
            @endunless
            @if($studentSheet)
                <section class="gc-task-history" aria-labelledby="task-history-title-{{ $task->id }}" data-task-attempt-history hidden>
                    <h3 id="task-history-title-{{ $task->id }}">Отправленные решения</h3>
                    <ul class="gc-attempt-list"></ul>
                    <button type="button" class="btn btn-outline-secondary gc-attempt-history__footer" data-task-attempt-more hidden>Показать ещё</button>
                </section>
            @endif
            <div class="gc-task-layout {{ $isManager ? 'is-teacher' : 'is-student' }} @if($task->is_quiz) is-quiz @endif">
                <div class="gc-task-answer">
                @if($isInsider && $task->is_quiz)
                    @include('steps.partials.quiz-response')
                @endif
            @if (\Request::is('insider/*'))

                @if (!$task->is_quiz)
                    @if ($course->teachers->contains($user) || $user->role == 'admin')
                        @php
                            $stepProgressStudents = $course->relationLoaded('statsStudents') ? $course->statsStudents : $course->students;
                        @endphp
                        <div class="gc-task-workspaces gc-section step-progress-card overflow-hidden" id="student-solutions-{{ $task->id }}" data-execution-review-navigation>
                                    <div data-execution-student-list @if($ownWorkspaceActive) hidden @endif>
                                    <div class="gc-section-header gc-section-header--between">
                                        <h3 class="h6 mb-0">Ученики</h3>
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
                                        <a class="gc-task-own-choice"
                                           href="{{ url('/insider/courses/'.$course->id.'/steps/'.$step->id).'?student='.Auth::id().'&task='.$task->id.'#task'.$task->id }}"
                                           data-execution-own-submission="#execution-own-workspace-{{ $task->id }}" data-student-id="{{ Auth::id() }}">
                                            <i class="far fa-user" aria-hidden="true"></i>
                                            <span>Мои решения</span>
                                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                                        </a>
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
                                               @if(!$solutionsCount) data-without-attempts @endif
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
                                                        <span data-chat-waiting @unless(($waitingStudents ?? collect())->get($task->id)?->has($student->id)) hidden @endunless><i class="far fa-comment" aria-hidden="true"></i> Ждёт ответа</span>
                                                        @if($solutionsCount > 0)
                                                            {{ $solutionsCount }} {{ trans_choice('сдача|сдачи|сдач', $solutionsCount) }}
                                                            @if($latestSubmittedAt)
                                                                · {{ $latestSubmittedAt->format('d.m H:i') }}
                                                            @endif
                                                        @else
                                                            Сдач пока нет
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
                            <div class="gc-task-review-panel" data-teacher-chat-panel>
                                @include('steps.partials.native-workspace', ['nativeTeacher' => true])
                                <div class="gc-task-own-workspace" @unless($ownWorkspaceActive) hidden @endunless>
                                    @if($nativeTaskChecker)
                                        @include('steps.partials.native-workspace', ['nativeTeacher' => true, 'nativeUser' => Auth::id(), 'nativeWorkspaceId' => 'execution-own-workspace-'.$task->id, 'nativeOwnWorkspace' => true])
                                    @else
                                        @if(!$task->is_code) @include('steps.partials.task_solution_form') @endif
                                        @include('steps.partials.native-workspace', ['nativeTeacher' => true, 'nativeUser' => Auth::id(), 'nativeWorkspaceId' => 'execution-own-workspace-'.$task->id, 'nativeOwnWorkspace' => true, 'nativeHistoryOnly' => true])
                                    @endif
                                </div>
                            </div>
                            <dialog class="gc-task-chat-dialog gc-teacher-chat-dialog" aria-label="Чат задачи"></dialog>
                        </div>
                    @endif
                    @if(!$nativeTaskChecker && !$isManager) @include('steps.partials.native-workspace', ['nativeTeacher' => false, 'nativeHistoryOnly' => true]) @endif
                @endif

                @if($isInsider && !$task->is_quiz && $nativeTaskChecker && !$isManager)
                    @include('steps.partials.native-workspace', ['nativeTeacher' => $isManager])
                @endif
                @if (!$task->is_quiz && !$task->is_code && !$nativeTaskChecker)
                    @if(!$isManager)
                        @include('steps.partials.task_solution_form')
                    @endif
                @endif
            @endif
                </div>
            </div>
            </div>
        </div>
    @endforeach
    @if(empty($studentAttempt))<div data-step-content-tabs data-zero-theory="{{ $zero_theory ? 'true' : 'false' }}" hidden></div>@endif
@endif
