<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Идёт контрольная · GeekClass</title>
    @include('layouts.partials.npm-vendor-assets')
</head>
<body>
<main class="container py-5">
    <h1>Идёт контрольная в GeekExam</h1>
    <p>{{ $message }}</p>
    <a class="btn btn-primary" href="https://exam.geekclass.ru">Вернуться в GeekExam</a>
    @if(request()->isMethod('get'))
        <a class="btn btn-link" href="{{ url()->current() }}">Проверить доступ</a>
    @endif
</main>
</body>
</html>
