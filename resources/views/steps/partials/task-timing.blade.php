@php($hasDeadlineChain = $deadline instanceof \App\Support\DeadlineRule && !empty($deadline->series))
@if($deadline && !$hasDeadlineChain)
    <span class="gc-task-metadata__deadline"><span class="gc-help-text">{{ $isManager ? 'Срок сдачи:' : 'Срок:' }}</span> <x-gc-deadline-label :expiration="$deadline->expiration" :rule="$deadline" :personal="!$isManager" :settled="!$isManager && $task->isDone($user->id)" /></span>
@endif
@if($earnedAchievementId)
    <a class="step-task-achievement-badge" href="{{ url('/insider/profile/'.$user->id.'#achievement-'.$earnedAchievementId) }}"><i class="fas fa-trophy" aria-hidden="true"></i> Достижение получено</a>
@endif
@if($hasDeadlineChain)<div class="gc-task-deadline-chain"><i class="fas fa-calendar-days" aria-hidden="true"></i> <x-gc-deadline-chain :rule="$deadline" /></div>@endif
@if($isInsider && !$isManager)
    @php($classBonus = $classBonuses[$task->id] ?? [])
    @if(!empty($classBonus['amount']))<p class="gc-help-note mt-2 mb-0"><i class="fas fa-coins gc-currency-icon" aria-hidden="true"></i> Решите полностью на занятии: +{{ $classBonus['amount'] }} GC. Сдайте до {{ \Carbon\Carbon::parse($classBonus['ends_at'])->setTimezone(config('app.timezone'))->format('H:i') }}.</p>@endif
@endif
