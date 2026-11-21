@extends('layouts.left-menu')
@section('title', 'Расход токенов ИИ')
@section('content')
@php
    $number = fn ($value) => $value === null ? '—' : number_format((int) $value, 0, ',', "\u{202F}");
@endphp
<div class="gc-form-layout">
    @include('settings.partials.masthead', ['title' => 'Расход токенов ИИ', 'description' => 'Вызовы через GeekClass. История до начала общего учёта доступна для школьного помощника.'])

    <form method="GET" action="{{ route('settings.ai.usage') }}" class="gc-card p-3 p-md-4 mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md">
                <label class="form-label" for="usage-period">Период</label>
                <select class="form-select" id="usage-period" name="period">
                    @foreach($periods as $value => $label)
                        <option value="{{ $value }}" @selected((string) $value === $period)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md">
                <label class="form-label" for="usage-purpose">Назначение</label>
                <select class="form-select" id="usage-purpose" name="purpose">
                    <option value="">Все назначения</option>
                    @foreach($purposes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['purpose'] ?? null) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md">
                <label class="form-label" for="usage-model">Модель</label>
                <select class="form-select" id="usage-model" name="model">
                    <option value="">Все модели</option>
                    @foreach($models as $model)
                        <option value="{{ $model }}" @selected(($filters['model'] ?? null) === $model)>{{ $model }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Применить</button>
            </div>
            @if($filters)
                <div class="col-auto"><a href="{{ route('settings.ai.usage') }}" class="btn btn-outline-secondary">Сбросить</a></div>
            @endif
        </div>
        @if($selectedUser !== null)
            <input type="hidden" name="user" value="{{ $filters['user'] }}">
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <span class="fw-semibold">{{ $selectedUser }}</span>
                <a href="{{ route('settings.ai.usage', Illuminate\Support\Arr::except($filters, 'user')) }}">Все пользователи</a>
            </div>
        @endif
    </form>

    @if((int) $totals->calls === 0)
        <div class="gc-card p-3 p-md-4">
            <h2 class="h5">Запросов пока нет</h2>
            <p class="gc-help-text mb-0">Статистика появится после вызовов ИИ, подходящих под выбранные фильтры.</p>
        </div>
    @else
        <section class="gc-card p-3 p-md-4 mb-3" aria-labelledby="usage-total-heading">
            <h2 class="h5 mb-3" id="usage-total-heading">Итого за период</h2>
            <dl class="row g-3 mb-0">
                @foreach(['tokens' => 'Учтено токенов', 'input' => 'Входные токены', 'output' => 'Выходные токены', 'calls' => 'Вызовы ИИ'] as $field => $label)
                    <div class="col-6 col-lg-3">
                        <dt class="gc-help-text fw-normal">{{ $label }}</dt>
                        <dd class="fs-4 fw-semibold text-break mb-0">{{ $number($totals->$field) }}</dd>
                    </div>
                @endforeach
            </dl>
            @if((int) $totals->calls_without_total > 0)
                <p class="gc-help-text mt-3 mb-0">Вызовы без полного счётчика токенов — {{ $number($totals->calls_without_total) }}. В сумму вошли доступные счётчики.</p>
            @endif
        </section>

        @foreach(['purpose' => 'По назначению', 'model' => 'По моделям', 'user' => 'По пользователям'] as $dimension => $heading)
            <section class="gc-card p-3 p-md-4 mb-3" aria-labelledby="usage-{{ $dimension }}-heading">
                <h2 class="h5 mb-3" id="usage-{{ $dimension }}-heading">{{ $heading }}</h2>
                <div class="table-responsive">
                    <table class="table align-middle mb-0" aria-labelledby="usage-{{ $dimension }}-heading">
                        <thead>
                            <tr>
                                <th scope="col">{{ ['purpose' => 'Назначение', 'model' => 'Модель', 'user' => 'Пользователь'][$dimension] }}</th>
                                <th scope="col" class="text-end">Токены</th>
                                <th scope="col" class="text-end">Вызовы</th>
                                <th scope="col" class="text-end">Без полного счётчика</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($breakdowns[$dimension] ?? $users as $row)
                                @php
                                    $value = $dimension === 'user' ? $row->user_id ?? 0 : $row->$dimension;
                                    $label = match ($dimension) {
                                        'purpose' => $purposes[$value] ?? $value,
                                        'model' => $value ?? 'Модель неизвестна',
                                        'user' => $row->name ?? ($row->user_id ? 'Пользователь #'.$row->user_id : 'Без пользователя'),
                                    };
                                @endphp
                                <tr>
                                    <th scope="row" class="fw-normal text-break">
                                        @if($value !== null && !isset($filters[$dimension]))
                                            <a href="{{ route('settings.ai.usage', array_merge($filters, [$dimension => $value])) }}">{{ $label }}</a>
                                        @else
                                            {{ $label }}
                                        @endif
                                        @if($dimension === 'user' && $row->name !== null)
                                            <span class="gc-help-text">#{{ $row->user_id }}</span>
                                        @endif
                                    </th>
                                    <td class="text-end">{{ $number($row->tokens) }}</td>
                                    <td class="text-end">{{ $number($row->calls) }}</td>
                                    <td class="text-end">{{ $number($row->calls_without_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($dimension === 'user' && $users->hasPages())
                    <div class="mt-3"><x-gc-page-navigation :paginator="$users" label="Страницы пользователей" /></div>
                @endif
            </section>
        @endforeach
    @endif
</div>
@endsection
