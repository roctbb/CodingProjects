@extends('layouts.left-menu')

@section('title')
    Сообщество
@endsection

@section('content')
    @php
        $visibleUsers = $users->sortByDesc(function ($member) {
            return $member->score();
        })->filter(function ($member) {
            return !$member->is_hidden;
        });
        $teachersCount = $visibleUsers->where('is_teacher', true)->count();
        $traineesCount = $visibleUsers->where('is_trainee', true)->count();
    @endphp

    <div class="cp-community-page">
        <section class="card border-0 rounded-4 shadow-sm cp-community-hero">
            <div class="card-body">
                <div class="cp-community-hero-top">
                    <div>
                        <h1 class="cp-community-title">Сообщество</h1>
                        <p class="cp-community-description">Участники школы, их роли и текущие ранги.</p>
                    </div>
                    <div class="cp-community-stats">
                        <span class="badge cp-community-stat-badge">Участников: {{ $visibleUsers->count() }}</span>
                        <span class="badge cp-community-stat-badge">Преподавателей: {{ $teachersCount }}</span>
                        <span class="badge cp-community-stat-badge">Стажёров: {{ $traineesCount }}</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-4 g-3 cp-community-grid">
            @foreach($visibleUsers as $member)
                <div class="col">
                    <article class="card h-100 cp-community-card">
                        <div class="card-body">
                            <div class="cp-community-card-head">
                                @if ($member->image!=null)
                                    <div class="rounded-circle img-circle cp-community-avatar"
                                         style='--profile-avatar-image: url("{{url('/media/'.$member->image)}}");'>
                                    </div>
                                @else
                                    <div class="rounded-circle img-circle cp-community-avatar"
                                         style='--profile-avatar-image: url("{{ url('images/user.jpg') }}");'>
                                    </div>
                                @endif
                                <div class="cp-community-card-meta">
                                    <h5 class="card-title mb-1">
                                        <a href="{{url('/insider/profile/'.$member->id)}}">{{ $member->name }}</a>
                                    </h5>
                                    <a tabindex="0" data-bs-toggle="popover" data-bs-trigger="focus" title="Ранги"
                                       data-bs-html="true" data-bs-content="{{\App\Rank::getRanksListHTML($member->rank())}}">
                                        <span class="badge rounded-pill text-bg-success profile-rank-badge">
                                            <i class="icon fa-solid fa-arrow-up"></i> {{$member->rank()->name}}
                                        </span>
                                    </a>
                                </div>
                            </div>
                            <div class="cp-community-card-badges">
                                @if ($member->is_trainee)
                                    <span class="badge rounded-pill text-bg-info">Стажер</span>
                                @endif
                                @if ($member->is_teacher)
                                    <span class="badge rounded-pill text-bg-info">Преподаватель</span>
                                @endif
                            </div>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.CPUI) {
                window.CPUI.initPopovers('[data-bs-toggle="popover"]');
                window.CPUI.initPopovers('.popover-dismiss', {trigger: 'focus'});
            }
        });
    </script>
@endsection
