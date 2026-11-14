@extends('layouts.left-menu')

@section('title')
    Выдача достижения: {{ $student->name }}
@endsection

@section('content')
    <div class="container-fluid px-0 solution-achievement-page">
        <div class="gc-page-header solution-achievement-page__header mb-3">
            <div class="solution-review-header__main min-width-0">
                <a class="solution-review-header__back"
                   href="{{ url('/insider/courses/'.$course->id.'/tasks/'.$task->id.'/student/'.$student->id.'#solution-'.$solution->id) }}"
                   title="К проверке"
                   aria-label="К проверке">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <div class="solution-review-header__copy min-width-0">
                    <h1 class="solution-review-header__title">Выдача достижения</h1>
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

        @php($initialVariant = $achievementPreviewVariants[0] ?? ['title' => '', 'description' => ''])
        <section class="gc-card p-3 p-md-4">
            <h2 class="h5 mb-2">Выберите идею достижения</h2>
            <p class="text-muted mb-3">Текст можно изменить перед выдачей.</p>
            <div class="gc-choice-grid mb-4" role="group" aria-label="Варианты достижения">
                @foreach($achievementPreviewVariants as $variantIndex => $variant)
                    <label class="gc-choice">
                        <input type="radio" name="achievement_variant" value="{{ $variantIndex }}" @checked($loop->first) data-achievement-choice data-achievement-form="achievement-award-form" data-title="{{ $variant['title'] }}" data-description="{{ $variant['description'] }}">
                        <span><strong>{{ $variant['title'] }}</strong><span class="text-muted d-block mt-1">{{ $variant['description'] }}</span></span>
                    </label>
                @endforeach
            </div>
            <form id="achievement-award-form" method="POST" action="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/achievement') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-9"><label class="form-label" for="achievement-title">Название</label><input id="achievement-title" name="title" class="form-control" maxlength="120" value="{{ old('title', $initialVariant['title']) }}" required></div>
                    <div class="col-md-3"><label class="form-label" for="achievement-coins">Награда, GC</label><input type="number" id="achievement-coins" name="coin_reward" class="form-control" min="0" max="1000" value="{{ old('coin_reward', 15) }}"></div>
                    <div class="col-12"><label class="form-label" for="achievement-description">Описание</label><textarea id="achievement-description" name="description" class="form-control" maxlength="1000" rows="3" required>{{ old('description', $initialVariant['description']) }}</textarea></div>
                </div>
            </form>
        </section>
        <x-gc-form-actions form="achievement-award-form" label="Выдать достижение" />
    </div>
@endsection
