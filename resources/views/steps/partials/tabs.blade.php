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
                    'class' => 'btn btn-outline-secondary btn-sm rounded-3 gc-icon-button',
                    'icon' => 'fas fa-pen',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/edit'),
                    'leading' => true,
                ],
                [
                    'kind' => 'button',
                    'title' => 'Добавить задачу',
                    'class' => 'btn btn-outline-secondary btn-sm rounded-3 gc-icon-button',
                    'icon' => 'fas fa-circle-plus',
                    'attributes' => [
                        'data-bs-toggle' => 'modal',
                        'data-bs-target' => '#exampleModal',
                    ],
                ],
                [
                    'kind' => 'link',
                    'title' => 'Режим занятия',
                    'class' => 'btn btn-outline-secondary btn-sm rounded-3 gc-icon-button',
                    'icon' => 'fas fa-desktop',
                    'href' => url('/insider/courses/' . $course->id . '/perform/' . $step->id),
                ],
                [
                    'kind' => 'link',
                    'title' => 'Поднять этап',
                    'class' => 'btn btn-outline-secondary btn-sm rounded-3 gc-icon-button',
                    'icon' => 'fas fa-arrow-up',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/lower'),
                ],
                [
                    'kind' => 'link',
                    'title' => 'Опустить этап',
                    'class' => 'btn btn-outline-secondary btn-sm rounded-3 gc-icon-button',
                    'icon' => 'fas fa-arrow-down',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/upper'),
                ],
                [
                    'kind' => 'link',
                    'title' => 'Удалить этап',
                    'class' => 'btn btn-outline-danger btn-sm rounded-3 gc-icon-button',
                    'icon' => 'fas fa-xmark',
                    'href' => url('/insider/courses/' . $course->id . '/steps/' . $step->id . '/delete'),
                    'attributes' => [
                        'data-confirm' => 'Вы уверены?',
                    ],
                ],
            ];
        @endphp
        @if (!$hasContentTabs && $isManager)
            <div class="step-header-actions" aria-label="Действия этапа">
                @foreach($stepActions as $action)
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
                @endforeach
            </div>
        @elseif ($hasContentTabs || $isManager)
        <ul class="nav nav-pills step-top-tabs"
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
                        $taskDeadline = null;

                        if ($isInsider) {
                            if ($task->isSubmitted($user->id)) {
                                if ($task->isFailed($user->id)) {
                                    $taskStatusIcon = 'fas fa-circle-xmark text-danger';
                                    $taskStatusTitle = 'Не выполнено';
                                } elseif ($task->isOnCheck($user->id)) {
                                    $taskStatusIcon = 'fas fa-hourglass-half text-warning';
                                    $taskStatusTitle = 'Ожидает проверки';
                                } elseif ($task->isFullDone($user->id)) {
                                    $taskStatusIcon = 'fas fa-circle-check text-success';
                                    $taskStatusTitle = 'Выполнено';
                                } else {
                                    $taskStatusIcon = 'fas fa-circle-exclamation text-warning';
                                    $taskStatusTitle = 'Требует доработки';
                                }
                            } else {
                                $taskDeadline = $task->getDeadline($course->id);
                            }

                            if (!$taskStatusIcon && isset($taskDeadline) && $taskDeadline) {
                                $deadline = $taskDeadline->expiration;
                                if (\Carbon\Carbon::now()->gt($deadline)) {
                                    $taskStatusIcon = 'fas fa-clock text-danger';
                                    $taskStatusTitle = 'Дедлайн';

	                                } elseif (\Carbon\Carbon::now()->addDays(3)->gt($deadline)) {
	                                    $taskStatusIcon = 'fas fa-clock text-warning';
	                                    $taskStatusTitle = 'Дедлайн';
	                                }
	                            }
	                        }
	                    @endphp
                    <li class="nav-item">
                        <a class="nav-link task-pill step-top-tab-link" data-bs-toggle="pill" id="tasks-tab{{$task->id}}"
                           href="#task{{$task->id}}"
                           aria-controls="tasks{{$task->id}}" aria-expanded="true" title="{{$task->name}}"><span class="step-top-tab-link__label">{{$key+1}}
                            . {{$task->name}}</span>
                            @if($task->is_star) <sup title="Избранная задача"><i class="fas fa-star" aria-hidden="true"></i></sup> @endif
                            @if($task->is_hidden) <sup title="Скрытая задача"><i class="fas fa-lock" aria-hidden="true"></i></sup> @endif
                            @if ($taskStatusIcon)
                                <sup><i class="{{ $taskStatusIcon }} {{ $taskStatusClass }}" title="{{ $taskStatusTitle }}" aria-hidden="true"></i></sup>
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
        @endif
    </div>

</div>
