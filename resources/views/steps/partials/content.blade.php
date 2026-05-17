@if (!$quizer)
    @foreach ($tasks as $key => $task)
        <div class="tab-pane fade @if (!$empty && $zero_theory && $one_tasker) show active @endif"
             id="task{{$task->id}}"
             role="tabpanel" aria-labelledby="tasks-tab{{$task->id}}">
            @php
                $geekpasteAttemptResetStatuses = $geekpasteAttemptResetStatuses ?? [];
                $isInsider = \Request::is('insider/*');
                $isManager = $isInsider && ($course->teachers->contains($user) || $user->role=='admin');
                $deadline = $isInsider ? $task->getDeadline($course->id) : null;
                $latestUserSolution = $isInsider && Auth::check() ? $task->latestSolutionForUser(Auth::id()) : null;
                $hasUserSolution = $latestUserSolution !== null;
                $geekpasteExtraAttemptCost = Auth::check() ? Auth::user()->geekPasteExtraAttemptCost() : \App\Services\GeekPasteClient::EXTRA_ATTEMPT_COST;
                $geekpasteAttemptResetStatus = $geekpasteAttemptResetStatuses[$task->id] ?? null;
                $canBuyGeekPasteExtraAttempt = $geekpasteAttemptResetStatus
                    && $user->role == 'student'
                    && !$isManager
                    && Auth::check()
                    && Auth::user()->balance() >= $geekpasteExtraAttemptCost;
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
                    <div class="gc-card step-task-card step-task-card--{{$taskType}} @if ($task->is_star) is-optional @endif @if ($hasUserSolution) is-submitted @endif overflow-hidden">
                        <div class="step-task-card__header gc-section-header">
                            <div class="min-width-0">
                                <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                                    <span class="step-task-type step-task-type--{{$taskType}}">
                                        <i class="{{$taskTypeIcon}}"></i>{{$taskTypeLabel}}
                                    </span>
                                    <h4 class="step-task-card__title fw-bold mb-0">{{$task->name}}</h4>
                                    <span class="badge rounded-pill bg-body-tertiary">{{$task->max_mark}} XP</span>
                                    @if($earnedAchievementId)
                                        <a class="badge rounded-pill step-task-achievement-badge"
                                           href="{{ url('/insider/profile/'.$user->id.'#achievement-'.$earnedAchievementId) }}"
                                           title="Достижение получено">
                                            <i class="fas fa-trophy"></i>Достижение
                                        </a>
                                    @endif
                                    @if ($deadline)
                                        @php
                                            $exp = $deadline->expiration;
                                            $deadlineClass = \Carbon\Carbon::now()->gt($exp)
                                                ? 'bg-danger-subtle text-danger-emphasis border-danger-subtle'
                                                : (\Carbon\Carbon::now()->addDays(3)->gt($exp)
                                                    ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle'
                                                    : 'bg-body-tertiary text-muted');
                                        @endphp
                                        <span class="badge rounded-pill border {{ $deadlineClass }}">
                                            <i class="fas fa-calendar-alt me-1"></i>{{ $deadline->expiration->format('d.m.Y') }}
                                        </span>
                                    @endif
                                    @if ($task->price > 0)
                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                            <i class="fas fa-coins me-1"></i>{{$task->price}}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            @if ($isManager)
                                <div class="step-task-actions d-flex flex-wrap justify-content-lg-end gap-1">
                                <a class="btn btn-outline-danger btn-sm rounded-3"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/delete')}}"
                                   title="Удалить задачу"
                                   data-confirm="Вы уверены?"><i class="icon ion-android-close"></i></a>
                                <a class="btn btn-outline-secondary btn-sm rounded-3"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/edit')}}"
                                   title="Редактировать задачу"><i class="icon ion-android-create"></i></a>
                                @include('steps/partials/deadline_modal')
                                <button type="button" title="Установить дедлайн" data-bs-toggle="modal"
                                   data-bs-target="#deadline-modal-{{$task->id}}"
                                   class="btn btn-outline-secondary btn-sm rounded-3"><i class="icon ion-ios-calendar"></i></button>
                                <a title="Фантомное решение (добавить пустое решение для всех студентов)"
                                   class="btn btn-outline-secondary btn-sm rounded-3"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/phantom')}}"><i class="icon ion-ios-color-wand"></i></a>
                                <button type="button"
                                        title="Пересказ решений"
                                        data-bs-toggle="modal"
                                        data-bs-target="#task-ai-summary-modal-{{$task->id}}"
                                        class="btn btn-outline-secondary btn-sm rounded-3">
                                    <i class="fas fa-newspaper"></i>
                                </button>
                                @if ($task->is_code)
                                    <a title="Перепроверить все решения (обнулить баллы и отправить последнее решение каждого студента на перепроверку)"
                                        class="btn btn-outline-secondary btn-sm rounded-3"
                                        href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/recheck-all')}}"
                                        data-confirm="Вы уверены? Это обнулит все баллы и отправит последние решения на перепроверку."><i class="icon ion-refresh"></i></a>
                                @endif
                                <a class="btn btn-outline-secondary btn-sm rounded-3" title="Сдвинуть вправо"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/right')}}"><i class="icon ion-arrow-right-c"></i></a>
                                <a class="btn btn-outline-secondary btn-sm rounded-3" title="Сдвинуть влево"
                                   href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/left')}}"><i class="icon ion-arrow-left-c"></i></a>
                                @if ($step->previousStep() != null)
                                    <a class="btn btn-outline-secondary btn-sm rounded-3" title="Перенести выше"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/up')}}"><i class="icon ion-arrow-up-c"></i></a>
                                @endif
                                @if ($step->nextStep() != null)
                                    <a class="btn btn-outline-secondary btn-sm rounded-3" title="Перенести ниже"
                                       href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/down')}}"><i class="icon ion-arrow-down-c"></i></a>
                                @endif
                                </div>
                            @endif
                        </div>
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
                                @if ($task->is_code)
                                    @if (!$blocked)
                                        <div class="step-code-submit mb-3 d-flex flex-wrap align-items-center gap-2">
                                            <a href="{{ config('services.geekpaste_url').'/?task_id=' . $task->id . '&course_id=' . $course->id }}"
                                               class="btn btn-success rounded-3 fw-semibold" target="_blank" rel="noopener">
                                                <i class="fas fa-code me-1"></i>Сдать решение
                                            </a>
                                            @if ($canBuyGeekPasteExtraAttempt)
                                                <form method="POST" action="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/geekpaste-extra-attempt') }}" class="d-inline-flex">
                                                    {{ csrf_field() }}
                                                    <button type="submit" class="btn btn-sm solution-special-action"
                                                            data-confirm="{{ $geekpasteExtraAttemptCost > 0 ? 'Купить одну дополнительную попытку GeekPaste за '.$geekpasteExtraAttemptCost.' GC?' : 'Использовать бесплатный сброс попытки GeekPaste от питомца?' }}">
                                                        <i class="fas fa-plus-circle"></i>
                                                        @if($geekpasteExtraAttemptCost > 0)
                                                            + попытка за {{ $geekpasteExtraAttemptCost }} GC
                                                        @else
                                                            + бесплатная попытка
                                                        @endif
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
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
                                            <label for="text{{$task->id}}" class="form-label fw-semibold mb-0">Ответ</label>
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
                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle fw-semibold" id="TSK_{{$task->id}}">0 XP</span>
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
                        <div class="row my-3">
                            <div class="col">
                                <div class="gc-card step-progress-card overflow-hidden">
                                    <div class="gc-section-header gc-section-header--between">
                                        <div>
                                            <h6 class="mb-0">Прогресс учеников</h6>
                                            <p class="text-muted small mb-0">Карточки решений по каждому ученику.</p>
                                        </div>
                                        <div class="input-group input-group-sm gc-search-box step-progress-search">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="search"
                                                   class="form-control"
                                                   placeholder="Найти ученика"
                                                   aria-label="Найти ученика"
                                                   data-step-progress-search
                                                   data-step-progress-grid="#step-progress-grid-{{ $task->id }}">
                                            <span class="input-group-text gc-search-box__count" data-step-progress-count>{{ $course->students->count() }} из {{ $course->students->count() }}</span>
                                        </div>
                                    </div>
                                    <div class="step-progress-grid-wrap">
                                        <div class="step-progress-grid" id="step-progress-grid-{{ $task->id }}">
                                        @foreach($course->students as $student)
                                            @php
                                                $filtered = $task->solutions->filter(function ($value) use ($student) {
                                                return $value->user_id == $student->id;
                                                });
                                                $bestSolution = \App\Solution::bestScoredIn($filtered);
                                                $latestSolution = $filtered->sortByDesc('submitted')->first();
                                                $blocked = $task->isBlocked($student->id, $course->id);
                                                if ($blocked) {
                                                    $mark = 0;
                                                    $need_check = false;
                                                    $class = 'bg-danger-subtle text-danger border border-danger-subtle fw-semibold';
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
                                                    $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary text-muted fw-semibold') : 'bg-body-tertiary text-muted fw-semibold';
                                                    $stateClass = ($need_check || $recheckRequested) ? 'is-pending' : ($bestSolution ? 'is-checked' : 'is-empty');
                                                    $stateLabel = $recheckRequested ? 'На перепроверку' : ($need_check ? 'На проверке' : ($bestSolution ? 'Проверено' : 'Нет решений'));
                                                    if ($mark >= $task->max_mark * 0.5)
                                                    {
                                                    $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary text-muted fw-semibold') : 'bg-body-tertiary text-muted fw-semibold';
                                                    }
                                                    if ($mark >= $task->max_mark * 0.7)
                                                    {
                                                    $class = $bestSolution ? $bestSolution->scoreBadgeClass('bg-body-tertiary text-muted fw-semibold') : 'bg-body-tertiary text-muted fw-semibold';
                                                    }
                                                    if ($need_check)
                                                    {
                                                    $class = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold';
                                                    }
                                                }
                                                $maxMark = max(1, (int) $task->max_mark);
                                                $progressPercent = min(100, max(0, round(((int) $mark / $maxMark) * 100)));
                                                $solutionsCount = $filtered->count();
                                                $searchTitle = trim($student->name . ' ' . ($student->activeCustomTitle() ?? '') . ' ' . $stateLabel . ' ' . $mark . ' ' . $task->max_mark);
                                            @endphp
                                            <a class="step-progress-cardlet {{ $stateClass }}"
                                               href="{{url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id)}}"
                                               target="_blank"
                                               data-step-progress-card
                                               data-step-progress-text="{{ $searchTitle }}">
                                                <span class="step-progress-cardlet__head">
                                                    <span class="step-progress-student">
                                                        <span class="text-truncate">{{$student->name}}</span>
                                                        @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
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
                                                            {{ $solutionsCount }} реш.
                                                            @if($latestSolution && $latestSolution->submitted)
                                                                · {{ $latestSolution->submitted->format('d.m H:i') }}
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
                            </div>
                        </div>
                    @endif
                    @include('steps.partials.task_solutions_list')
                @endif

                @if (!$task->is_quiz and !$task->is_code)
                    @include('steps.partials.task_solution_form')
                @endif
            @endif
        </div>
    @endforeach
    <div data-step-content-tabs data-zero-theory="{{ $zero_theory ? 'true' : 'false' }}" hidden></div>
@endif
