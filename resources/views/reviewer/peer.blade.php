<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Peer review - {{ $task->name }}</title>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('gc-theme');
                if (theme) document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {}
        })();
	    </script>
	    <link rel="stylesheet" href="{{ asset('build/css/app.css') }}">
	</head>
<body class="peer-review">
<main class="container-xl py-4">
    <section class="gc-card gc-page-header mb-3">
        <div class="min-width-0">
            <h2 class="mb-1">Peer review</h2>
            <p class="mb-0 text-muted text-truncate">{{ $task->name }}</p>
        </div>
        <div class="row g-2 flex-nowrap peer-review-stats">
            <div class="col">
                <div class="gc-summary-tile peer-review-stat">
                    <strong>{{ $students->count() }}</strong>
                    <span>участников</span>
                </div>
            </div>
            <div class="col">
                <div class="gc-summary-tile peer-review-stat">
                    <strong>2</strong>
                    <span>проверки на работу</span>
                </div>
            </div>
        </div>
    </section>

    <section class="gc-card overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 gc-data-table">
                <thead class="text-uppercase small">
                <tr>
                    <th class="text-muted">ID</th>
                    <th>Ученик</th>
                    <th>Reviewer 1</th>
                    <th>Reviewer 2</th>
                    <th>Решение</th>
                </tr>
                </thead>
                <tbody>
                @foreach($students as $id => $student)
                    <tr>
                        <td class="text-muted">{{ $id }}</td>
                        <td class="fw-semibold">
                            <span class="d-inline-flex align-items-center gap-1 min-width-0">
                                <span class="text-truncate">{{ $student->name }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $student, 'compact' => true])
                            </span>
                        </td>
                        <td>
                            <span class="d-inline-flex align-items-center gap-1 min-width-0">
                                <span class="text-truncate">{{ $student->reviewer1->name }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $student->reviewer1, 'compact' => true])
                            </span>
                        </td>
                        <td>
                            <span class="d-inline-flex align-items-center gap-1 min-width-0">
                                <span class="text-truncate">{{ $student->reviewer2->name }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $student->reviewer2, 'compact' => true])
                            </span>
                        </td>
                        <td>
                            @if($student->solution == 'Нет')
                                <span class="badge rounded-pill bg-body-tertiary">Нет</span>
                            @else
                                <span class="badge rounded-pill bg-body-tertiary">Есть</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach($students as $id => $student)
        <section class="gc-card peer-review-sheet overflow-hidden mb-4">
            <div class="gc-section-header gc-section-header--between">
                <div>
                    <span class="text-muted small">ID {{ $id }}</span>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <h3 class="mb-0">{{ $student->name }}</h3>
                        @include('profile.partials.custom_title_badge', ['profileUser' => $student])
                    </div>
                </div>
                <span class="badge rounded-pill bg-body-tertiary">протокол проверки</span>
            </div>

            <div class="gc-section-header">
                <div class="markdown peer-review-task-brief">
                    <h4 class="mt-0">Условие задачи и правила оценивания</h4>
                    {!! parsedown($task->text) !!}
                </div>
            </div>

            <div class="peer-review-solutions p-3 p-md-4">
                <h4>Решения для проверки</h4>
                <p class="text-muted mb-3">Для каждого решения оставьте оценку по критериям и короткий комментарий.</p>

                @forelse($student->works as $solution)
                    <article class="peer-review-work rounded-3 overflow-hidden mb-3">
                        <div class="peer-review-work__header">
                            <strong class="text-truncate">Работа ID {{ $ids[$solution->user->id] }}</strong>
                            <span class="text-muted d-inline-flex align-items-center gap-1 min-width-0">
                                <span class="text-truncate">{{ $solution->user->name }}</span>
                                @include('profile.partials.custom_title_badge', ['profileUser' => $solution->user, 'compact' => true])
                            </span>
                        </div>
                        <div class="peer-review-work__body p-3 bg-body">
                            {!! nl2br(e($solution->text)) !!}
                        </div>
                        <div class="peer-review-comment-box">
                            <span>Комментарий и критерии</span>
                        </div>
                        <div class="peer-review-score-box">
                            <span>Итого XP</span>
                        </div>
                    </article>
                @empty
                    <div class="peer-review-empty">Для этого ученика нет решений для проверки.</div>
                @endforelse
            </div>
        </section>
        <div class="peer-review-page-break"></div>
    @endforeach
</main>
</body>
</html>
