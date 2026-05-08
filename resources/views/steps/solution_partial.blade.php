{{-- Will be replaced by solution.vue --}}
<div class="row mt-4">
    <div class="col">
        <div class="gc-card step-solution-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="icon ion-ios-clock-outline" style="margin-right:0.3rem;opacity:0.7;"></i>{{ $solution->submitted->format('d.M.Y H:i')}}</span>
                <div>
                    @if ($solution->mark!=null)
                        <span class="badge bg-primary">Очков опыта: {{$solution->mark}}</span>
                    @else
                        <span class="badge bg-secondary">Не проверено</span>
                    @endif
                </div>
            </div>
            <div class="card-body">

                <div data-linkify>
                    {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))  !!}
                </div>

                <br><br>
                @if ($solution->mark!=null)
                    <p>
                        <span class="badge bg-light text-dark">Проверено: {{$solution->checked}}
                            , {{$solution->teacher->name}}</span>
                    </p>
                    <p class="small" data-linkify>
                        {!!  nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false)) !!}
                    </p>

                    @if ($solution->mark != $solution->task->max_mark and $solution->task->is_code)
                        @if ($solution->recheck_requested)
                            <p><span class="badge bg-primary">Запрошена перепроверка</span></p>
                        @elseif (!$task->isFullDone(Auth::User()->id))
                            <p>
                                <a href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution/'. $solution->id . '/recheck') }}"
                                   class="btn btn-primary">Попросить перепроверить</a></p>
                        @endif

                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
