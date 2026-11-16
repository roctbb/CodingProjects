@extends('layouts.left-menu')

@section('title', 'Планировщик — '.$course->name)

@section('head')
    @php
        $plannerScriptPath = public_path('build/js/coursePlanner.js');
        $plannerScriptVersion = file_exists($plannerScriptPath) ? filemtime($plannerScriptPath) : null;
    @endphp
    <script type="module" src="{{ asset('build/js/coursePlanner.js') }}{{ $plannerScriptVersion ? '?v='.$plannerScriptVersion : '' }}"></script>
@endsection

@section('content')
<x-gc-course-header :course="$course" active="plan" />
<div id="course-planner" data-api="{{ url('/insider/courses/'.$course->id.'/planner/api') }}" data-deadline-api="{{ url('/insider/courses/'.$course->id.'/deadline-policies/task') }}" data-today="{{ now('Europe/Moscow')->toDateString() }}" data-view="calendar">
    <h2 class="visually-hidden">План занятий</h2>
    @if (!$ready)
        <div class="alert alert-info">Для запуска планировщика администратору необходимо применить новые миграции базы данных.</div>
    @else
    <x-gc-workspace-toolbar class="planner-toolbar" role="group" aria-label="Период и действия плана">
        <div class="planner-range">
            <div class="planner-range-arrows">
                <button class="planner-icon-button" type="button" data-action="previous-range" aria-label="Предыдущая неделя">‹</button>
                <button class="planner-icon-button" type="button" data-action="next-range" aria-label="Следующая неделя">›</button>
            </div>
            <details id="planner-range-picker" class="planner-entry-menu planner-range-picker">
                <summary class="planner-period-button"><span id="planner-period-label">Период</span><span aria-hidden="true">⌄</span></summary>
                <div class="planner-menu planner-period-menu">
                    <label for="planner-from">Начало периода</label><input class="form-control form-control-sm" type="date" id="planner-from">
                    <label for="planner-to">Конец периода</label><input class="form-control form-control-sm" type="date" id="planner-to">
                    <button type="button" class="planner-apply-range" data-action="apply-range">Показать период</button>
                </div>
            </details>
            <button class="planner-quiet-button planner-today" type="button" data-action="today" aria-label="Текущая неделя" title="Текущая неделя"><i class="fas fa-calendar-day" aria-hidden="true"></i><span>Сегодня</span></button>
        </div>
        <label class="planner-view-control"><span class="visually-hidden">Вид плана занятий</span><select id="planner-view" class="form-select form-select-sm"><option value="calendar">Неделя</option><option value="overview">Обзор курса</option></select></label>
        <div class="planner-toolbar-actions">
        <div class="planner-calendar-actions" data-calendar-only>
            <button class="btn btn-outline-secondary btn-sm planner-catalog-shortcut" type="button" data-action="open-catalog" aria-haspopup="dialog" aria-controls="planner-catalog"><i class="fas fa-book-open me-1" aria-hidden="true"></i>Материалы</button>
            <details class="planner-entry-menu planner-add-menu">
                <summary class="btn btn-primary btn-sm">+ Добавить</summary>
                <div class="planner-menu">
                    <button type="button" class="planner-mobile-materials" data-action="open-catalog" aria-haspopup="dialog" aria-controls="planner-catalog">Материалы к занятию</button>
                    <button type="button" data-action="add-slot">Занятие на дату</button>
                    <button type="button" data-action="add-session">Занятие без даты</button>
                    <button type="button" data-action="add-deadline">Дедлайн работы</button>
                </div>
            </details>
        </div>
        <details class="planner-entry-menu planner-course-menu">
            <summary class="planner-icon-button" aria-label="Действия с планом"><i class="fas fa-ellipsis" aria-hidden="true"></i></summary>
            <div class="planner-menu">
                <button type="button" data-action="undo" disabled>Отменить изменение</button>
                <button type="button" data-action="history">История изменений</button>
                <button type="button" data-action="settings">Расписание курса</button>
                <button type="button" data-action="providers">Подключённые провайдеры</button>
            </div>
        </details>
    </div>
        </x-gc-workspace-toolbar>
    <span id="planner-save-state" class="visually-hidden" aria-live="polite"></span>
    <div id="planner-status" class="planner-status" role="status" aria-live="polite">Загрузка плана…</div>

    <section id="planner-calendar-panel" aria-label="Календарь занятий">
        <div class="planner-workspace">
            <main class="planner-timeline" aria-label="Занятия курса">
                <details class="planner-backlog" id="planner-backlog-panel">
                    <summary><span>Без даты <span id="planner-backlog-count" class="planner-count">0</span></span><span class="planner-backlog-caption">Занятия, которые нужно распределить <span aria-hidden="true">⌄</span></span></summary>
                    <div class="planner-backlog-content">
                        <div id="planner-backlog" class="planner-drop" data-drop-backlog></div>
                        <button type="button" class="planner-inline-button" data-action="add-session">+ Занятие без даты</button>
                    </div>
                </details>
                <div id="planner-calendar" tabindex="0" aria-label="Календарь занятий"></div>
                <div class="planner-timeline-footer"><span id="planner-range-caption"></span><span id="planner-layer-caption"></span></div>
            </main>

            <dialog class="gc-dialog planner-catalog" id="planner-catalog" aria-labelledby="planner-catalog-title" aria-describedby="planner-target-title">
                <div class="planner-catalog-heading"><div><h2 id="planner-catalog-title">Добавить материалы</h2><div class="planner-catalog-target" aria-live="polite"><strong id="planner-target-title">Выберите занятие</strong><span id="planner-target-subtitle"></span></div></div><button type="button" class="planner-close" data-action="close-catalog" aria-label="Закрыть выбор материалов">×</button></div>
                <div class="planner-catalog-filters">
                    <label for="planner-provider">Провайдер</label><select id="planner-provider" class="form-select form-select-sm"></select>
                    <div class="planner-search"><i class="fas fa-magnifying-glass" aria-hidden="true"></i><input id="planner-search" class="form-control form-control-sm" type="search" aria-label="Поиск материалов" maxlength="200" placeholder="Найти материал…" autocomplete="off"></div>
                    <label class="visually-hidden" for="planner-type">Тип материала</label><select id="planner-type" class="form-select form-select-sm"><option value="">Все типы</option></select>
                    <div id="planner-catalog-breadcrumb"></div>
                </div>
                <div id="planner-catalog-status" class="planner-catalog-status" aria-live="polite"></div>
                <div id="planner-catalog-list" class="planner-catalog-list"></div>
                <div class="planner-pagination"><button type="button" class="planner-quiet-button" data-action="catalog-prev" disabled aria-label="Предыдущая страница материалов">← Предыдущая страница</button><span id="planner-page">Страница 1</span><button type="button" class="planner-quiet-button" data-action="catalog-next" disabled aria-label="Следующая страница материалов">Следующая страница →</button></div>
                <div class="gc-dialog-actions"><span id="planner-catalog-selected" class="gc-help-text" aria-live="polite">Ничего не выбрано</span><button type="button" class="btn btn-outline-secondary btn-sm" data-action="close-catalog">Закрыть</button><button type="button" class="btn btn-primary btn-sm" data-action="catalog-attach-selected" aria-label="Добавить выбранные материалы на занятие" disabled>Добавить</button></div>
                <p id="planner-catalog-error" class="text-danger small mb-0" role="alert"></p>
            </dialog>
        </div>
    </section>

    <section id="planner-overview-panel" aria-label="Обзор курса" hidden>
        <section class="planner-overview">
            <div class="planner-section-heading"><div><h2>Нагрузка по неделям</h2><p>За выбранный период</p></div><select id="planner-metric" class="form-select form-select-sm" aria-label="Показатель графика"></select></div>
            <div id="planner-chart"></div>
            <p class="planner-overview-help">Дедлайны показывают текущие сроки курса. Баллы разных систем считаются отдельно.</p>
        </section>
        <section class="planner-overview">
            <div class="planner-section-heading"><div><h2>Материалы по урокам</h2><p id="planner-summary-caption"></p></div></div>
            <p class="planner-overview-help">Количество уникальных материалов каждого провайдера. Нажмите на число, чтобы увидеть материалы и даты занятий.</p>
            <div class="table-responsive"><table class="table table-sm align-middle planner-summary"><thead id="planner-summary-head"></thead><tbody id="planner-summary-body"></tbody></table></div>
        </section>
    </section>

    <div id="planner-history" hidden></div>
    <dialog id="planner-dialog" class="gc-dialog" aria-labelledby="planner-dialog-title"><form id="planner-dialog-form"><header><h2 id="planner-dialog-title"></h2><button type="button" class="planner-close" data-action="close-dialog" aria-label="Закрыть">×</button></header><div id="planner-dialog-body"></div><p id="planner-dialog-error" class="text-danger small" role="alert"></p><footer class="gc-dialog-actions"><button type="button" class="btn btn-outline-secondary" data-action="close-dialog">Отмена</button><button type="submit" class="btn btn-primary" id="planner-dialog-submit">Сохранить</button></footer></form></dialog>
    @endif
</div>
@endsection
