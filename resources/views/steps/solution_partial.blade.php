{{-- Will be replaced by solution.vue --}}
@php
    $solutionScoreBadgeClass = $solution->scoreBadgeClass();
@endphp
<div class="row my-3 step-solution-row">
    <div class="col">
        <div class="gc-card step-solution-card overflow-hidden">
            <div class="gc-section-header gc-section-header--responsive">
                <div class="min-width-0">
                    <span class="gc-eyebrow">Решение</span>
                    <span class="text-muted small"><i class="icon ion-ios-clock-outline me-1 opacity-75"></i>{{ $solution->submitted->format('d.m.Y H:i')}}</span>
                </div>
                <div class="flex-shrink-0">
                    @if ($solution->mark!=null)
                        @if($solution->hasActiveDeadlinePenalty())
                            <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{$solution->mark}} XP после штрафа</span>
                        @else
                            <span class="badge rounded-pill {{ $solutionScoreBadgeClass }}">{{$solution->mark}} XP</span>
                        @endif
                    @else
                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-semibold">Не проверено</span>
                    @endif
                </div>
            </div>
            <div class="p-3">
                <div class="solution-block">
                    <div class="solution-block__label">Ответ</div>
                    <div class="solution-answer" data-linkify>
                        {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))  !!}
                    </div>
                </div>

                @if ($solution->mark!=null)
                    <div class="solution-block mt-3">
                        <div class="solution-block__label">Проверка</div>
                        <div class="solution-feedback" data-linkify>
                            <div class="small text-muted mb-1">Проверено: {{$solution->checked}}, {{$solution->teacher->name}}</div>
                            @if(trim((string) $solution->comment) !== '')
                                {!!  nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false)) !!}
                            @else
                                <span class="text-muted fst-italic">Комментарий не оставлен</span>
                            @endif
                        </div>
                    </div>

                    @if ($solution->mark != $solution->task->max_mark and $solution->task->is_code)
                        @if ($solution->recheck_requested)
                            <span class="badge rounded-pill bg-body-tertiary mt-3">Запрошена перепроверка</span>
                        @elseif (!$task->isFullDone(Auth::User()->id))
                            <a href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution/'. $solution->id . '/recheck') }}"
                               class="btn btn-success btn-sm rounded-3 fw-semibold mt-3">Попросить перепроверить</a>
                        @endif

                    @endif

                    @include('steps.partials.deadline_penalty', ['solution' => $solution])
                    @include('steps.partials.xp_booster', ['solution' => $solution])
                @endif
            </div>
        </div>
    </div>
</div>
