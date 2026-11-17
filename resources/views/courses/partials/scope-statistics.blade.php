@if($scopeStatistics)
<details class="gc-journal-overview mb-3" data-scope-statistics>
    <summary class="gc-summary-line"><h2 class="gc-section-title mb-0">Сводка результатов</h2><span class="gc-help-text">@if($scopeStatistics['summary']['percent'] !== null){{ $scopeStatistics['summary']['percent'] }}% · @endif{{ $scopeStatistics['summary']['solved'] }} / {{ $scopeStatistics['summary']['tasks'] }} задач решено <i class="fas fa-chevron-down ms-2" aria-hidden="true"></i></span></summary>
    <div class="gc-card p-3 p-md-4 gc-stack gc-stack--compact">
        <div class="gc-report-overview mb-0">
            <span>Прогресс: <strong>{{ $scopeStatistics['summary']['percent'] !== null ? $scopeStatistics['summary']['percent'].'%' : 'Без оценки' }}</strong></span>
            <span>Баллы: <strong>{{ $scopeStatistics['summary']['points'] }} / {{ $scopeStatistics['summary']['maximum'] }} XP</strong></span>
            <span>Просрочено: <strong>{{ $scopeStatistics['summary']['overdue'] }}</strong></span>
        </div>
        <p class="gc-help-text mb-0">Результаты текущего состава на открытых для каждого ученика уроках. Переводы не меняют историю решений. Для сравнения темпа выбирайте одинаковые уроки в журнале.</p>
        <form method="GET" class="row g-2 align-items-end">
            @include('courses.partials.scope-input')
            @if(request('chapter'))<input type="hidden" name="chapter" value="{{ request('chapter') }}">@endif
            <div class="col-12 col-sm"><label class="form-label" for="scope-from">Активность с</label><input class="form-control" type="date" name="from" id="scope-from" value="{{ $scopeStatistics['from'] }}" required></div>
            <div class="col-12 col-sm"><label class="form-label" for="scope-to">По</label><input class="form-control" type="date" name="to" id="scope-to" value="{{ $scopeStatistics['to'] }}" required></div>
            <div class="col-auto"><button type="submit" class="btn btn-outline-secondary">Показать</button></div>
            <div class="col-12"><span class="gc-help-text">Отправляли решения за период: <strong>{{ $scopeStatistics['summary']['active'] }} из {{ $scopeStatistics['summary']['students'] }}</strong></span></div>
        </form>
        @if($scopeStatistics['comparison'])
        <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th scope="col">Подгруппа</th><th scope="col">Ученики</th><th scope="col">Решено задач</th><th scope="col">Прогресс</th><th scope="col">Активны за период</th></tr></thead><tbody>
        @foreach($scopeStatistics['comparison'] as $row)<tr><th scope="row"><a class="gc-text-action" href="{{ request()->fullUrlWithQuery(['subgroup'=>$row['id'], 'student'=>null]) }}">{{ $row['name'] }}</a></th><td>{{ $row['students'] }}</td><td>{{ $row['solved'] }} / {{ $row['tasks'] }}</td><td>{{ $row['percent'] !== null ? $row['percent'].'%' : 'Без оценки' }}</td><td>{{ $row['active'] }}</td></tr>@endforeach
        </tbody></table></div>
        @endif
    </div>
</details>
@endif
