@if($task->is_star)
    <span class="gc-task-title-meta" title="За решение начисляется дополнительный опыт"><i class="far fa-star" aria-hidden="true"></i> Необязательная</span>
@endif
@if($task->price > 0)
    <span class="gc-task-title-meta gc-task-reward" title="Награда за полное решение"><i class="fas fa-coins" aria-hidden="true"></i> {{ $task->price }} GC</span>
@endif
