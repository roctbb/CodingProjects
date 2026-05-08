<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Peer review - {{ $task->name }}</title>
    <link rel="stylesheet" href="{{ asset('build/css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('build/css/legacy-theme.css') }}">
</head>
<body class="peer-review">
<main class="peer-review-page">
    <section class="management-header gc-card mb-3">
        <div class="min-width-0">
            <h2 class="mb-1">Peer review</h2>
            <p class="mb-0 text-muted text-truncate">{{ $task->name }}</p>
        </div>
        <div class="assessment-summary">
            <div><strong>{{ $students->count() }}</strong><span>участников</span></div>
            <div><strong>2</strong><span>проверки на работу</span></div>
        </div>
    </section>

    <section class="gc-card management-table-card mb-4">
        <div class="table-responsive">
            <table class="table management-table mb-0">
                <thead>
                <tr>
                    <th>ID</th>
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
                        <td class="fw-semibold">{{ $student->name }}</td>
                        <td>{{ $student->reviewer1->name }}</td>
                        <td>{{ $student->reviewer2->name }}</td>
                        <td>
                            @if($student->solution == 'Нет')
                                <span class="badge bg-light text-dark">Нет</span>
                            @else
                                <span class="badge bg-success">Есть</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @foreach($students as $id => $student)
        <section class="gc-card peer-review-sheet mb-4">
            <div class="peer-review-sheet__header">
                <div>
                    <span class="text-muted small">ID {{ $id }}</span>
                    <h3 class="mb-0">{{ $student->name }}</h3>
                </div>
                <span class="badge bg-primary">протокол проверки</span>
            </div>

            <div class="peer-review-task markdown">
                <h4>Условие задачи и правила оценивания</h4>
                {!! parsedown($task->text) !!}
            </div>

            <div class="peer-review-solutions">
                <h4>Решения для проверки</h4>
                <p class="text-muted">Для каждого решения оставьте оценку по критериям и короткий комментарий.</p>

                @forelse($student->works as $solution)
                    <article class="peer-review-work">
                        <div class="peer-review-work__header">
                            <strong>Работа ID {{ $ids[$solution->user->id] }}</strong>
                            <span class="text-muted">{{ $solution->user->name }}</span>
                        </div>
                        <div class="peer-review-work__body">
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
                    <div class="alert alert-light border mb-0">Для этого ученика нет решений для проверки.</div>
                @endforelse
            </div>
        </section>
        <div class="peer-review-page-break"></div>
    @endforeach
</main>
</body>
</html>
