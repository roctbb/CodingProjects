@if($pulse_keys->has($student->id) || $task_keys->has($student->id))
    <details class="gc-disclosure gc-disclosure--inline gc-report-activity" data-report-activity>
        <summary>Активность: просмотры и опыт</summary>
        <div class="report-chart-legend mt-3">
            @if($pulse_keys->has($student->id))<span><i class="report-chart-dot report-chart-dot--pulse" aria-hidden="true"></i> просмотры</span>@endif
            @if($task_keys->has($student->id))<span><i class="report-chart-dot report-chart-dot--tasks" aria-hidden="true"></i> опыт, XP</span>@endif
        </div>
        <div id="pulse{{ $student->id }}" class="report-chart w-100" role="img" aria-label="Активность {{ $student->name }}: просмотры материалов и полученный опыт"
             data-plotly-report-chart data-pulse-keys='{{ $pulse_keys->get($student->id, '[]') }}' data-pulse-values='{{ $pulse_values->get($student->id, '[]') }}'
             @if($task_keys->has($student->id)) data-task-keys='{{ $task_keys[$student->id] }}' data-task-values='{{ $task_values[$student->id] }}' @endif></div>
    </details>
@endif
