{{-- Will be replaced by solution.vue --}}
<div class="row" style="margin-top: 15px; margin-bottom: 15px;">
    <div class="col">
        <div class="card">
            <div class="card-header">
                Дата сдачи: {{ $solution->submitted->format('d.M.Y H:i')}}
                <div class="float-right">
                    @if ($solution->mark!=null)
                        <span class="badge badge-primary">Очков опыта: {{$solution->mark}}</span>
                        <br>
                    @else
                        <span class="badge badge-secondary">Решение еще не проверено</span>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @if ($task->is_code)
                    <pre><code class="hljs python">{{$solution->text}}</code></pre>
                @else
                    {!! nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->text)), false))  !!}
                @endif
                <br><br>
                @if ($solution->mark!=null)
                    <p>
                        <span class="badge badge-light">Проверено: {{$solution->checked}}
                            , {{$solution->teacher->name}}</span>
                    </p>
                    <p class="small">
                        {!!  nl2br(e(str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', str_replace(' ', '&nbsp;', $solution->comment)), false)) !!}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>