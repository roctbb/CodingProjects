@php
            $isInsider = \Request::is('insider/*');
            $isManager = $isInsider && isset($course) && $course && ($course->teachers->contains($user) || $user->role == 'admin');
            $hasTheoryTab = count($tasks) != 0 && !$zero_theory && !$quizer;
            $hasTaskTabs = count($tasks) != 0 && !$quizer && (!$one_tasker || !$zero_theory);
            $hasContentTabs = $hasTheoryTab || $hasTaskTabs;

        @endphp
@if ($hasContentTabs)
<div class="step-section-toolbar">
    <x-gc-tab-strip>
    <ul class="nav gc-page-tabs step-top-tabs" id="pills-tab" role="tablist" data-step-content-tabs data-zero-theory="{{ $zero_theory ? 'true' : 'false' }}">
            @if ($hasTheoryTab)
                <li class="nav-item">
                    <a class="nav-link @if(!$initialTaskId) active @endif step-top-tab-link" data-bs-toggle="pill" id="theory-tab" href="#theory" role="tab"
                       aria-controls="theory" aria-selected="{{ $initialTaskId ? 'false' : 'true' }}">Теория</a>
                </li>
            @endif
            @if ($hasTaskTabs)
                @foreach ($tasks as $key => $task)
                    <li class="nav-item">
                        <a class="nav-link task-pill step-top-tab-link @if ($initialTaskId == $task->id) active @endif" data-bs-toggle="pill" id="tasks-tab{{$task->id}}"
                           href="#task{{$task->id}}"
                           role="tab" aria-controls="task{{$task->id}}" aria-selected="{{ $initialTaskId == $task->id ? 'true' : 'false' }}" title="{{$task->name}}"><span class="step-top-tab-link__label">{{$key+1}}
                            . {{$task->name}}</span>
                            @if($task->is_star) <sup title="Необязательная задача"><i class="fas fa-star" aria-hidden="true"></i></sup> @endif
                            @if($task->is_hidden) <sup title="Скрытая задача"><i class="fas fa-lock" aria-hidden="true"></i></sup> @endif
                            @if($isInsider && !$isManager)
                                <x-gc-task-state :task="$task" :viewer="$user" :course="$course" :outcome="$taskOutcomes[$task->id] ?? null" compact />
                            @endif
                        </a>
                    </li>
                @endforeach
            @endif
    </ul>
    </x-gc-tab-strip>
</div>
@endif
