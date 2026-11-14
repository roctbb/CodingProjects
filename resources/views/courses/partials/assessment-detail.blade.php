@php $lessonTasks = $lesson->steps->flatMap->tasks; @endphp
<div class="gc-card overflow-hidden">
    <div class="table-responsive assessment-table-wrap" role="region" aria-label="Задачи урока {{ $lesson->name }}, прокручивается по горизонтали" tabindex="0">
        <table class="table table-sm align-middle assessment-table gc-data-table mb-0" id="assessment-detail-table">
            <thead><tr><th class="assessment-sticky-col" scope="col">Ученик</th>
                @foreach($lessonTasks as $task)
                    <th class="assessment-task-head" scope="col" data-table-column>{{ $task->name }}<small>{{ $task->max_mark }} XP @if($task->is_star)<span title="Дополнительная задача">★</span>@endif @if($task->only_class)<i class="fas fa-users" title="В классе"></i>@endif @if($task->only_remote)<i class="fas fa-at" title="Дистанционно"></i>@endif</small></th>
                @endforeach
            </tr></thead>
            <tbody>
                @foreach($journalRows as $journalRow)
                                    @php
                                        $student = $journalRow['student'];
                                        $cells = $journalRow['cells'];
                                    @endphp
                    <tr data-assessment-detail-row="{{ $student->id }}">
                        <th scope="row" class="assessment-sticky-col assessment-student-cell"><a class="gc-quiet-link" href="{{ url('/insider/profile/'.$student->id) }}">{{ $student->name }}</a></th>
                        @foreach($lessonTasks as $task)
                            @php
                                ['mark' => $mark, 'state' => $state] = $cells[$task->id];
                                $stateLabel = $assessmentStates[$state][0];
                            @endphp
                            <td data-assessment-state="{{ $state }}"><a class="gc-assessment-cell-action" target="_blank" rel="noopener" aria-label="{{ $student->name }} · {{ $task->name }}: {{ $mark }} из {{ $task->max_mark }} · {{ $stateLabel }}" href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id) }}"><x-gc-assessment-score :mark="$mark" :maximum="$task->max_mark" :state="$state" :label="$stateLabel" /></a></td>
                        @endforeach
                    </tr>
                @endforeach
                <tr data-assessment-detail-empty @if($journalRows->isNotEmpty()) hidden @endif><td colspan="{{ $lessonTasks->count() + 1 }}" class="text-muted text-center py-4">{{ $journalRows->isEmpty() ? 'В курсе пока нет учеников.' : 'Ничего не найдено.' }}</td></tr>
            </tbody>
        </table>
    </div>
    <x-gc-table-navigation target="assessment-detail-table" label="Задачи урока" />
</div>
