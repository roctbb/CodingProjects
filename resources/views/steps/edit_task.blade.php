@extends('layouts.left-menu')

@section('title')
    Изменение задачи "{{$task->name}}"
@endsection

@section('content')
    <div class="container-xl px-0">
        <div class="gc-card gc-page-header mb-3">
            <div class="min-width-0">
                <a class="assessment-back-link" href="{{ url('/insider/courses/'.$course->id.'/steps/'.$task->step->id.'#task'.$task->id) }}"><i class="icon ion-chevron-left"></i> К задаче</a>
                <h2 class="fw-bold lh-sm mb-1">Изменение задачи</h2>
                <p class="mb-0 text-muted text-truncate">{{$task->step->name}}</p>
            </div>
        </div>

        <form id="task-edit-form" method="POST" enctype="multipart/form-data">
            {{ csrf_field() }}

            <div class="row g-3 align-items-start">
                <div class="col-12 col-lg-8 d-grid gap-3">
            <div class="gc-card overflow-hidden">
                <div class="gc-section-header">
                    <h5 class="mb-1">Основное</h5>
                    <p class="text-muted small mb-0">Название, очки и тип задачи.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label for="name" class="form-label">Название</label>
                            <input id="name" type="text" class="form-control rounded-3" name="name" value="{{old('name', $task->name)}}"
                                   required>
                            @if ($errors->has('name'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('name') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-sm-6 col-lg-2">
                            <label for="max_mark" class="form-label">XP</label>
                            <input type="number" name="max_mark" class="form-control rounded-3" id="max_mark"
                                   value="{{old('max_mark', $task->max_mark)}}" min="0" max="1000" required/>
                            @if ($errors->has('max_mark'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('max_mark') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-sm-6 col-lg-2">
                            <label for="price" class="form-label">Премия</label>
                            <input type="number" name="price" class="form-control rounded-3" id="price"
                                   value="{{old('price', $task->price)}}" min="0"/>
                            @if ($errors->has('price'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('price') }}</strong>
                                </span>
                            @endif
                        </div>

                        <div class="col-12">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="gc-switch-card form-check form-switch h-100">
                                        <input type="checkbox" class="form-check-input ms-0 me-2" id="is_star" name="is_star" value="on"
                                               @if (old('is_star', $task->is_star ? 'on' : '') == 'on') checked @endif>
                                        <span class="form-check-label">Дополнительная</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="gc-switch-card form-check form-switch h-100">
                                        <input type="checkbox" class="form-check-input ms-0 me-2" id="is_hidden" name="is_hidden" value="on"
                                               @if (old('is_hidden', $task->is_hidden ? 'on' : '') == 'on') checked @endif>
                                        <span class="form-check-label">Скрытая задача</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="gc-switch-card form-check form-switch h-100">
                                        <input type="checkbox" class="form-check-input ms-0 me-2" id="is_code" name="is_code" value="on"
                                               @if (old('is_code', $task->is_code ? 'on' : '') == 'on') checked @endif>
                                        <span class="form-check-label">Автопроверка</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="gc-switch-card form-check form-switch h-100">
                                        <input type="checkbox" class="form-check-input ms-0 me-2" id="xp_booster_enabled" name="xp_booster_enabled" value="on"
                                               @if (old('xp_booster_enabled', $task->xp_booster_enabled ? 'on' : '') == 'on') checked @endif>
                                        <span class="form-check-label">Бустер +5 XP</span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="gc-switch-card form-check form-switch h-100">
                                        <input type="checkbox" class="form-check-input ms-0 me-2" id="generates_ai_achievement" name="generates_ai_achievement" value="on"
                                               @if (old('generates_ai_achievement', $task->generates_ai_achievement ? 'on' : '') == 'on') checked @endif>
                                        <span class="form-check-label">AI-достижение</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <label for="ai_achievement_instruction" class="form-label">Фокус AI-достижения</label>
                            <textarea id="ai_achievement_instruction"
                                      name="ai_achievement_instruction"
                                      class="form-control rounded-3"
                                      rows="2"
                                      placeholder="Например: отмечай необычную идею, аккуратность кода или красивый ход">{{ old('ai_achievement_instruction', $task->ai_achievement_instruction) }}</textarea>
                            @if ($errors->has('ai_achievement_instruction'))
                                <span class="text-danger small d-block mt-1">
                                    <strong>{{ $errors->first('ai_achievement_instruction') }}</strong>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="gc-card overflow-hidden">
                <div class="gc-section-header">
                    <h5 class="mb-1">Условие</h5>
                    <p class="text-muted small mb-0">Текст задачи, который видит ученик.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                        <label for="text" class="form-label mb-0">Текст</label>
                        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Улучшение текста задачи">
                            <button type="button" class="btn btn-outline-primary rounded-start-3" data-improve-text="fix_typos" data-field-id="text">
                                <i class="icon ion-android-checkbox-outline"></i> Опечатки
                            </button>
                            <button type="button" class="btn btn-outline-info" data-improve-text="improve_style" data-field-id="text">
                                <i class="icon ion-android-create"></i> Стиль
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-end-3" data-improve-text="both" data-field-id="text">
                                <i class="icon ion-android-star"></i> Всё
                            </button>
                        </div>
                    </div>
                    <textarea id="text" class="form-control rounded-3" data-markdown-editor
                              name="text">{{old('text', $task->text)}}</textarea>
                            @if ($errors->has('text'))
                        <span class="text-danger small d-block mt-1">
                            <strong>{{ $errors->first('text') }}</strong>
                        </span>
                            @endif
                </div>
            </div>

            <div class="gc-card overflow-hidden">
                <div class="gc-section-header">
                    <h5 class="mb-1">Проверка и ответ</h5>
                    <p class="text-muted small mb-0">Авторское решение и короткий ответ для квизов.</p>
                </div>
                <div class="p-3 p-md-4">
                    <div class="mb-3">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
                            <label for="solution" class="form-label mb-0">Решение</label>
                            <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Улучшение решения">
                                <button type="button" class="btn btn-outline-primary rounded-start-3" data-improve-text="fix_typos" data-field-id="solution">
                                    <i class="icon ion-android-checkbox-outline"></i> Опечатки
                                </button>
                                <button type="button" class="btn btn-outline-info" data-improve-text="improve_style" data-field-id="solution">
                                    <i class="icon ion-android-create"></i> Стиль
                                </button>
                                <button type="button" class="btn btn-outline-secondary rounded-end-3" data-improve-text="both" data-field-id="solution">
                                    <i class="icon ion-android-star"></i> Всё
                                </button>
                            </div>
                        </div>
                        <textarea id="solution" class="form-control rounded-3" data-markdown-editor
                                  name="solution">{{old('solution', $task->solution)}}</textarea>
                        @if ($errors->has('solution'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('solution') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="mb-0">
                            <label for="answer" class="form-label">Ответ</label>
                        <input type="text" name="answer" class="form-control rounded-3" id="answer"
                               value="{{old('answer', $task->answer)}}"/>

                            @if ($errors->has('answer'))
                            <span class="text-danger small d-block mt-1">
                                <strong>{{ $errors->first('answer') }}</strong>
                            </span>
                            @endif
                    </div>
                </div>
            </div>

                </div>

                <div class="col-12 col-lg-4">
                    <aside class="gc-card course-create-aside p-3 p-md-4 sticky-lg-top">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="gc-icon-tile flex-shrink-0"><i class="fas fa-tasks"></i></span>
                            <div class="min-width-0">
                                <h5 class="mb-1">Задача</h5>
                                <p class="mb-0 text-muted small text-truncate">{{ $task->step->lesson->name }}</p>
                            </div>
                        </div>
                        <div class="d-grid gap-2 mb-3">
                            <div class="gc-info-tile"><span>Название</span><strong>{{ $task->name }}</strong></div>
                            <div class="gc-info-tile"><span>Оценивание</span><strong>{{ $task->max_mark }} XP @if($task->price > 0) · {{ $task->price }} монет @endif</strong></div>
                            <div class="gc-info-tile"><span>Тип</span><strong>{{ $task->is_code ? 'Автопроверка' : ($task->is_quiz ? 'Квиз' : 'Ручная проверка') }}</strong></div>
                            @if($task->is_hidden || $task->is_star || $task->xp_booster_enabled || $task->generates_ai_achievement)
                                <div class="d-flex flex-wrap gap-1">
                                    @if($task->is_hidden)
                                        <span class="badge rounded-pill bg-body-tertiary">Скрытая</span>
                                    @endif
                                    @if($task->is_star)
                                        <span class="badge rounded-pill bg-body-tertiary">Дополнительная</span>
                                    @endif
                                    @if($task->xp_booster_enabled)
                                        <span class="badge rounded-pill bg-body-tertiary"><i class="fas fa-wand-magic-sparkles me-1"></i>Бустер +5 XP</span>
                                    @endif
                                    @if($task->generates_ai_achievement)
                                        <span class="badge rounded-pill bg-body-tertiary"><i class="fas fa-trophy me-1"></i>AI-достижение</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <button type="submit" form="task-edit-form" class="btn btn-success rounded-3 fw-semibold w-100">Сохранить</button>
                    </aside>
                </div>
            </div>
        </form>
    </div>
@endsection
@push('editor')
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/easymde-bridge.js') }}"></script>
@endpush
