@extends('layouts.left-menu')

@section('title', 'Проверка')

@section('content')
    <div class="gc-title-row gc-title-row--center">
        <div class="min-width-0">
            <span class="text-muted text-uppercase fw-bold font-monospace small d-block mb-1">workspace</span>
            <h2 class="mb-1">Проверка</h2>
        </div>

        <a class="btn btn-outline-secondary rounded-3 fw-semibold px-3 py-2" href="{{ url('/insider/courses') }}">
            <i class="fas fa-graduation-cap me-1"></i>Мои курсы
        </a>
    </div>

    <section class="gc-card pending-review-page">
        <div class="pending-review-page__head">
            <div class="d-flex align-items-center gap-2 min-width-0">
                <span class="pending-review-page__head-icon"><i class="fas fa-code-branch"></i></span>
                <h5 class="mb-0">Непроверенные решения</h5>
            </div>
            <div class="d-inline-flex align-items-center gap-2 flex-shrink-0">
                <span class="badge rounded-pill bg-body-tertiary">{{ $pendingSolutions->total() }}</span>
                @if($pendingSolutions->total())
                    <form method="post" action="{{ url('/insider/reviews/reset-pending') }}" onsubmit="return confirm('Пропустить все непроверенные решения из очереди?');">
                        {{ csrf_field() }}
                        <button type="submit" class="btn btn-outline-secondary btn-sm rounded-3">Пропустить все</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="pending-review-page__list">
            @forelse($pendingSolutions as $solution)
                @php
                    $reviewUrl = $solution->task_id
                        ? url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/student/'.$solution->user_id.'#solution-'.$solution->id)
                        : url('insider/courses/'.$solution->course_id);
                @endphp
                <div class="pending-review-card">
                    <a class="pending-review-card__main" href="{{ $reviewUrl }}" title="{{ $solution->user->name }} · {{ $solution->task->name ?? 'Задача' }}">
                        <x-gc-avatar :user="$solution->user" size="md" class="pending-review-card__avatar" alt="" />

                        <span class="pending-review-card__body min-width-0">
                            <span class="pending-review-card__top">
                                <span class="pending-review-card__student">{{ $solution->user->name }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $solution->user, 'compact' => true])
                            </span>
                            <span class="pending-review-card__task">{{ $solution->task->name ?? 'Задача' }}</span>
                            <span class="pending-review-card__course">{{ $solution->course->name ?? 'Курс' }}</span>
                            <span class="pending-review-card__time">
                                @if($solution->submitted)
                                    <span>{{ $solution->submitted->diffForHumans() }}</span>
                                    <span>{{ $solution->submitted->format('d.m.Y H:i') }}</span>
                                @else
                                    <span>Без даты</span>
                                @endif
                            </span>
                        </span>
                    </a>

                    <span class="pending-review-card__actions">
                        <a class="pending-review-card__arrow" href="{{ $reviewUrl }}" title="Открыть решение" aria-label="Открыть решение">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <form class="pending-review-card__skip" method="post" action="{{ url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/solution/'.$solution->id.'/skip-review') }}">
                            {{ csrf_field() }}
                            <button type="submit"
                                    class="pending-review-card__skip-button"
                                    title="Пропустить решение"
                                    aria-label="Пропустить решение">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="pending-review-page__empty">
                    <span class="pending-review-page__head-icon"><i class="fas fa-check"></i></span>
                    <span>Непроверенных решений нет.</span>
                </div>
            @endforelse
        </div>

        @if($pendingSolutions->hasPages())
            <div class="pending-review-page__pagination">
                {{ $pendingSolutions->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </section>
@endsection
