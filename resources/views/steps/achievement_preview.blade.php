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
                    <i class="fas fa-chevron-left"></i>
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
                <button type="submit" class="btn btn-sm gc-action-button rounded-3 fw-medium">
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
        </div>

        <div class="solution-achievement-preview solution-achievement-preview--page">
            <div class="solution-achievement-preview__head">
                <span class="solution-achievement-preview__icon">
                    <i class="fas fa-trophy"></i>
                </span>
                <div class="min-width-0">
                    <div class="solution-achievement-preview__eyebrow">Варианты достижения</div>
                    <h6 class="solution-achievement-preview__title">Выберите вариант или поправьте текст перед выдачей</h6>
                </div>
            </div>

            <div class="solution-achievement-preview__variants">
                @foreach($achievementPreviewVariants as $variantIndex => $variant)
                    <form method="post"
                          action="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/achievement') }}"
                          class="solution-achievement-variant">
                        {{ csrf_field() }}
                        <span class="solution-achievement-variant__icon">
                            <i class="fas fa-trophy"></i>
                        </span>
                        <div class="solution-achievement-variant__fields min-width-0">
                            <label class="form-label" for="achievement-title-{{ $solution->id }}-{{ $variantIndex }}">Название</label>
                            <input id="achievement-title-{{ $solution->id }}-{{ $variantIndex }}"
                                   type="text"
                                   name="title"
                                   class="form-control rounded-3"
                                   maxlength="120"
                                   value="{{ old('title', $variant['title']) }}"
                                   required>
                            <label class="form-label" for="achievement-description-{{ $solution->id }}-{{ $variantIndex }}">Описание</label>
                            <textarea id="achievement-description-{{ $solution->id }}-{{ $variantIndex }}"
                                      name="description"
                                      class="form-control rounded-3"
                                      rows="3"
                                      maxlength="1000"
                                      required>{{ old('description', $variant['description']) }}</textarea>
                            <div class="solution-achievement-variant__reward">
                                <label class="form-label" for="achievement-coins-{{ $solution->id }}-{{ $variantIndex }}">GC</label>
                                <input id="achievement-coins-{{ $solution->id }}-{{ $variantIndex }}"
                                       type="number"
                                       name="coin_reward"
                                       class="form-control rounded-3"
                                       min="0"
                                       max="1000"
                                       value="{{ old('coin_reward', 15) }}">
                            </div>
                        </div>
                        <div class="solution-achievement-variant__actions">
                            <button type="submit" class="btn btn-success btn-sm rounded-3 fw-medium">Выдать</button>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>
    </div>
@endsection
