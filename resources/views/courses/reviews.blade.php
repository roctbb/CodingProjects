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
            <span class="badge rounded-pill bg-body-tertiary">{{ $pendingSolutions->total() }}</span>
        </div>

        <div class="pending-review-page__list">
            @forelse($pendingSolutions as $solution)
                @php
                    $reviewUrl = $solution->task_id
                        ? url('insider/courses/'.$solution->course_id.'/tasks/'.$solution->task_id.'/student/'.$solution->user_id.'#solution-'.$solution->id)
                        : url('insider/courses/'.$solution->course_id);
                @endphp
                <a class="pending-review-card" href="{{ $reviewUrl }}" title="{{ $solution->user->name }} · {{ $solution->task->name ?? 'Задача' }}">
                    <x-gc-avatar :user="$solution->user" size="md" class="pending-review-card__avatar" alt="" />

                    <span class="pending-review-card__body min-width-0">
                        <span class="pending-review-card__top">
                            <span class="pending-review-card__student">{{ $solution->user->name }}</span>
                            @include('profile.partials.custom_title_badge', ['profileUser' => $solution->user, 'compact' => true])
                        </span>
                        <span class="pending-review-card__task">{{ $solution->task->name ?? 'Задача' }}</span>
                        <span class="pending-review-card__course">{{ $solution->course->name ?? 'Курс' }}</span>
                    </span>

                    <span class="pending-review-card__time">
                        @if($solution->submitted)
                            <span>{{ $solution->submitted->diffForHumans() }}</span>
                            <span>{{ $solution->submitted->format('d.m.Y H:i') }}</span>
                        @else
                            <span>Без даты</span>
                        @endif
                    </span>

                    <span class="pending-review-card__arrow"><i class="fas fa-arrow-right"></i></span>
                </a>
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
