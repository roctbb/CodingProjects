{{-- Will be replaced by solution.vue --}}
<div class="row cp-row-gap-y">
    <div class="col">
        <div class="card">
            <div class="card-header">
                Дата сдачи: {{ $solution->submitted->format('d.M.Y H:i')}}
                <div class="steps-solution-header-meta">
                    @if ($solution->mark!=null)
                        <span class="badge text-bg-primary">Очков опыта: {{$solution->mark}}</span>
                    @else
                        <span class="badge text-bg-secondary">Решение еще не проверено</span>
                    @endif
                </div>
            </div>
            <div class="card-body">

                {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))  !!}

                <br><br>
                @if ($solution->mark!=null)
                    <p>
                        <span class="badge text-bg-light">Проверено: {{$solution->checked}}
                            , {{$solution->teacher->name}}</span>
                    </p>
                    <p class="small">
                        {!!  nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false)) !!}
                    </p>

                    @if ($solution->mark != $solution->task->max_mark and $solution->task->is_code)
                        @if ($solution->recheck_requested)
                            <p><span class="badge text-bg-primary">Запрошена перепроверка</span></p>
                        @elseif (!$task->isFullDone(Auth::User()->id))
                            <p>
                                <a href="#"
                                   data-action-url="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution/'. $solution->id . '/recheck') }}"
                                   data-action-method="POST"
                                   class="btn btn-primary">Попросить перепроверить</a></p>
                        @endif

                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
