<div class="row">
    <div class="col">
        @php
            $isInsider = \Request::is('insider/*');
            $isManager = $isInsider && isset($course) && $course && ($course->teachers->contains($user) || $user->role == 'admin');
            $hasTheoryTab = count($tasks) != 0 && !$zero_theory && !$quizer;
            $hasTaskTabs = count($tasks) != 0 && !$quizer && (!$one_tasker || !$zero_theory);
            $hasContentTabs = $hasTheoryTab || $hasTaskTabs;

            $stepActions = !$isManager ? [] : [
                [
                    'kind' => 'link',
                    'title' => 'Редактировать этап',
                    'class' => 'btn btn-success btn-sm p-2',
                    'icon' => 'icon ion-android-create',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/edit'),
                    'leading' => true,
                ],
                [
                    'kind' => 'button',
                    'title' => 'Добавить задачу',
                    'class' => 'btn btn-success btn-sm p-2',
                    'icon' => 'icon ion-android-add-circle',
                    'attributes' => [
                        'data-bs-toggle' => 'modal',
                        'data-bs-target' => '#exampleModal',
                    ],
                ],
                [
                    'kind' => 'link',
                    'title' => 'Режим занятия',
                    'class' => 'btn btn-success btn-sm p-2',
                    'icon' => 'icon ion-android-desktop',
                    'href' => url('/insider/courses/' . $course->id . '/perform/' . $step->id),
                ],
                [
                    'kind' => 'link',
                    'title' => 'Поднять этап',
                    'class' => 'btn btn-success btn-sm p-2',
                    'icon' => 'ion-arrow-up-c',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/lower'),
                ],
                [
                    'kind' => 'link',
                    'title' => 'Опустить этап',
                    'class' => 'btn btn-success btn-sm p-2',
                    'icon' => 'ion-arrow-down-c',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/upper'),
                ],
                [
                    'kind' => 'link',
                    'title' => 'Удалить этап',
                    'class' => 'btn btn-danger btn-sm p-2',
                    'icon' => 'ion-close-round',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/delete'),
                    'attributes' => [
                        'data-confirm' => 'Вы уверены?',
                    ],
                ],
            ];
        @endphp
        <ul class="nav nav-pills justify-content-end step-top-tabs @if (!$hasContentTabs) step-top-tabs--actions-only @endif"
            id="pills-tab" role="tablist">
            @if ($hasTheoryTab)
                <li class="nav-item">
                    <a class="nav-link active step-top-tab-link" data-bs-toggle="pill" id="theory-tab" href="#theory" role="tab"
                       aria-controls="theory" aria-expanded="true">0. Теория</a>
                </li>
            @endif
            @if ($hasTaskTabs)
                @foreach ($tasks as $key => $task)
                    @php
                        $taskStatusIcon = null;
                        $taskStatusTitle = null;
                        $taskStatusClass = null;

                        if ($isInsider) {
                            if ($task->isSubmitted($user->id)) {
                                if ($task->isFailed($user->id)) {
                                    $taskStatusIcon = url('images/icons/icons8-cancel-48.png');
                                    $taskStatusTitle = 'Не выполнено';
                                } elseif ($task->isOnCheck($user->id)) {
                                    $taskStatusIcon = url('images/icons/icons8-historical-48.png');
                                    $taskStatusTitle = 'Ожидает проверки';
                                } elseif ($task->isFullDone($user->id)) {
                                    $taskStatusIcon = url('images/icons/icons8-checkmark-48.png');
                                    $taskStatusTitle = 'Выполнено';
                                } else {
                                    $taskStatusIcon = url('images/icons/icons8-error-48.png');
                                    $taskStatusTitle = 'Требует доработки';
                                }
                            } elseif ($task->getDeadline($course->id)) {
                                $deadline = $task->getDeadline($course->id)->expiration;
                                if (\Carbon\Carbon::now()->gt($deadline)) {
                                    $taskStatusIcon = url('images/icons/deadline.png');
                                    $taskStatusTitle = 'Дедлайн';
                                    $taskStatusClass = 'border border-danger rounded';
                                } elseif (\Carbon\Carbon::now()->addDays(3)->gt($deadline)) {
                                    $taskStatusIcon = url('images/icons/deadline.png');
                                    $taskStatusTitle = 'Дедлайн';
                                    $taskStatusClass = 'border border-warning rounded';
                                }
                            }
                        }
                    @endphp
                    <li class="nav-item">
                        <a class="nav-link task-pill step-top-tab-link" data-bs-toggle="pill" id="tasks-tab{{$task->id}}"
                           href="#task{{$task->id}}"
                           aria-controls="tasks{{$task->id}}" aria-expanded="true" title="{{$task->name}}"><span class="step-top-tab-link__label">{{$key+1}}
                            . {{$task->name}}</span>
                            @if($task->is_star) <sup>*</sup> @endif
                            @if($task->is_hidden) <sup title="Скрытая задача">🔒</sup> @endif
                            @if ($taskStatusIcon)
                                <sup><img class="{{ $taskStatusClass }}" title="{{ $taskStatusTitle }}"
                                          src="{{ $taskStatusIcon }}" height="20"/></sup>
                            @endif
                        </a>
                    </li>
                @endforeach
            @endif
            @if ($isManager)
                @foreach($stepActions as $action)
                    <li class="nav-item mx-1 step-tabs-action @if (!empty($action['leading'])) step-tabs-actions-start @endif">
                        @if ($action['kind'] === 'button')
                            <button type="button" class="{{ $action['class'] }}" title="{{ $action['title'] }}"
                                @foreach(($action['attributes'] ?? []) as $attribute => $value)
                                    {{ $attribute }}="{{ $value }}"
                                @endforeach
                            >
                                <i class="{{ $action['icon'] }}"></i>
                            </button>
                        @else
                            <a href="{{ $action['href'] }}" class="{{ $action['class'] }}" title="{{ $action['title'] }}"
                                @foreach(($action['attributes'] ?? []) as $attribute => $value)
                                    {{ $attribute }}="{{ $value }}"
                                @endforeach
                            ><i class="{{ $action['icon'] }}"></i></a>
                        @endif
                    </li>
                @endforeach
            @endif
        </ul>
    </div>

</div>
