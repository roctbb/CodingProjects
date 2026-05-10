@extends('layouts.left-menu')

@section('title')
    Выдача достижения: {{ $student->name }}
@endsection

@section('content')
    <div class="container-fluid px-0 solution-achievement-page">
        <div class="gc-card gc-page-header solution-achievement-page__header mb-3">
            <div class="solution-review-header__main min-width-0">
                <a class="solution-review-header__back"
                   href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id.'#solution-'.$solution->id) }}"
                   title="К проверке"
                   aria-label="К проверке">
                    <i class="icon ion-chevron-left"></i>
                </a>
                <div class="solution-review-header__copy min-width-0">
                    <h2 class="solution-review-header__title">Выдача достижения</h2>
                    <p class="solution-review-header__meta mt-1">
                        <span class="text-truncate">{{ $student->name }}</span>
                        @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                        <span>· {{ $task->name }}</span>
                    </p>
                </div>
            </div>
            <form method="post"
                  action="{{ url('insider/courses/'.$course->id.'/tasks/'.$task->id.'/solution/'.$solution->id.'/achievement-preview') }}"
                  data-confirm="Сгенерировать новые варианты достижения для этого решения?"
                  data-fullscreen-loading
                  data-loading-message="Генерирую новые варианты достижения">
                {{ csrf_field() }}
                <button type="submit" class="btn btn-sm gc-action-button rounded-3 fw-semibold">
                    <i class="fas fa-sync-alt me-1"></i>Еще варианты
                </button>
            </form>
        </div>

        <div class="gc-card solution-achievement-context mb-3">
            <div class="solution-achievement-context__item">
                <span>Кому</span>
                <strong>{{ $student->name }}</strong>
            </div>
            <div class="solution-achievement-context__item">
                <span>Задача</span>
                <strong>{{ $task->name }}</strong>
            </div>
            <div class="solution-achievement-context__item">
                <span>Решение</span>
                <strong>#{{ $solution->id }} · {{ optional($solution->submitted)->format('d.m.Y H:i') }}</strong>
            </div>
            @if(!empty($achievementPreview['model']))
                <div class="solution-achievement-context__item">
                    <span>Модель</span>
                    <strong>{{ $achievementPreview['model'] }}</strong>
                </div>
            @endif
        </div>

        <div class="solution-achievement-preview solution-achievement-preview--page">
            <div class="solution-achievement-preview__head">
                <span class="solution-achievement-preview__icon">
                    @if(!empty($achievementPreview['visual_svg']))
                        {!! $achievementPreview['visual_svg'] !!}
                    @else
                        <i class="{{ $achievementPreview['icon_class'] }}"></i>
                    @endif
                </span>
                <div class="min-width-0">
                    <div class="solution-achievement-preview__eyebrow">Варианты достижения</div>
                    <h6 class="solution-achievement-preview__title">Выберите вариант или поправьте текст перед выдачей</h6>
                </div>
            </div>

            <div class="solution-achievement-preview__variants">
                @foreach($achievementPreviewVariants as $variantIndex => $variant)
                    @php
                        $variantIconKey = $variant['icon_key'] ?? 'sparkles';
                        $variantVisualKey = $variant['visual_key'] ?? '';
                        $variantSvgIcon = \App\Achievement::sanitizeSvgIcon($variant['svg_icon'] ?? null);
                        $variantSvg = $variantSvgIcon ?: \App\Achievement::svgForVisualKey($variantVisualKey);
                    @endphp
                    <form method="post"
                          action="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/achievement') }}"
                          class="solution-achievement-variant">
                        {{ csrf_field() }}
                        <span class="solution-achievement-variant__icon">
                            @if($variantSvg)
                                {!! $variantSvg !!}
                            @else
                                <i class="{{ $achievementIconOptions[$variantIconKey] ?? $achievementIconOptions['sparkles'] }}"></i>
                            @endif
                        </span>
                        <div class="solution-achievement-variant__fields min-width-0">
                            <label class="form-label" for="achievement-title-{{ $solution->id }}-{{ $variantIndex }}">Название</label>
                            <input id="achievement-title-{{ $solution->id }}-{{ $variantIndex }}"
                                   type="text"
                                   name="title"
                                   class="form-control rounded-3"
                                   maxlength="120"
                                   value="{{ old('title', $variant['title'] ?? 'Сильное решение') }}"
                                   required>
                            <label class="form-label" for="achievement-description-{{ $solution->id }}-{{ $variantIndex }}">Описание</label>
                            <textarea id="achievement-description-{{ $solution->id }}-{{ $variantIndex }}"
                                      name="description"
                                      class="form-control rounded-3"
                                      rows="3"
                                      required>{{ old('description', $variant['description'] ?? 'За сильное решение задачи.') }}</textarea>
                            <div class="solution-achievement-variant__selects">
                                <label class="form-label" for="achievement-icon-{{ $solution->id }}-{{ $variantIndex }}">Иконка</label>
                                <select id="achievement-icon-{{ $solution->id }}-{{ $variantIndex }}"
                                        name="icon_key"
                                        class="form-select rounded-3">
                                    @foreach($achievementIconOptions as $iconKey => $iconClass)
                                        <option value="{{ $iconKey }}" @if(old('icon_key', $variantIconKey) === $iconKey) selected @endif>{{ $iconKey }}</option>
                                    @endforeach
                                </select>
                                <label class="form-label" for="achievement-visual-{{ $solution->id }}-{{ $variantIndex }}">SVG</label>
                                <select id="achievement-visual-{{ $solution->id }}-{{ $variantIndex }}"
                                        name="visual_key"
                                        class="form-select rounded-3">
                                    @foreach($achievementVisualOptions as $visualKey => $visualLabel)
                                        <option value="{{ $visualKey }}" @if(old('visual_key', $variantVisualKey) === $visualKey) selected @endif>{{ $visualLabel }}</option>
                                    @endforeach
                                </select>
                                <label class="form-label" for="achievement-coins-{{ $solution->id }}-{{ $variantIndex }}">GC</label>
                                <input id="achievement-coins-{{ $solution->id }}-{{ $variantIndex }}"
                                       type="number"
                                       name="coin_reward"
                                       class="form-control rounded-3"
                                       min="0"
                                       max="1000"
                                       value="{{ old('coin_reward', 15) }}">
                            </div>
                            <input type="hidden" name="tone" value="{{ $variant['tone'] ?? '' }}">
                            <input type="hidden" name="solution_source" value="{{ $variant['solution_source'] ?? '' }}">
                            <input type="hidden" name="language" value="{{ $variant['language'] ?? '' }}">
                            <input type="hidden" name="model" value="{{ $variant['model'] ?? '' }}">
                            @if($variantSvgIcon)
                                <input type="hidden" name="svg_icon" value="{{ $variantSvgIcon }}">
                            @endif
                        </div>
                        <div class="solution-achievement-variant__actions">
                            <button type="submit" class="btn btn-success btn-sm rounded-3 fw-semibold">Выдать</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endsection
