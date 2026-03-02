@extends('layouts.left-menu')
@section('title')
    Модерация темы: {{ $theme->name }}
@endsection
@section('content')
    <div class="container">
        <h2>Модерация темы: {{ $theme->name }}</h2>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">{{ $theme->name }}</h5>
                        <p class="card-text">{{ $theme->description }}</p>
                        <p class="card-text"><strong>Автор:</strong> {{ $theme->user->name }}</p>
                        <p class="card-text"><strong>Цена:</strong> {{ $theme->price }} монет</p>
                        <p class="card-text"><strong>Создана:</strong> {{ $theme->created_at->format('d.m.Y H:i') }}</p>
                        <p class="card-text"><strong>Статус:</strong>
                            @if($theme->isPending())
                                <span class="badge badge-warning">Ожидает проверки</span>
                            @elseif($theme->isApproved())
                                <span class="badge badge-success">Одобрена</span>
                            @elseif($theme->isBanned())
                                <span class="badge badge-danger">Забанена</span>
                            @endif
                        </p>
                        @if($theme->moderator)
                            <p class="card-text"><strong>Модератор:</strong> {{ $theme->moderator->name }}</p>
                            <p class="card-text"><strong>Дата модерации:</strong> {{ $theme->moderated_at ? $theme->moderated_at->format('d.m.Y H:i') : 'Неизвестно' }}</p>
                        @endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>CSS код</h5>
                    </div>
                    <div class="card-body">
                        <pre><code>{{ $theme->css() }}</code></pre>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>JavaScript код</h5>
                    </div>
                    <div class="card-body">
                        <pre><code>{{ $theme->js() }}</code></pre>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>Действия модератора</h5>
                    </div>
                    <div class="card-body">
                        <form action="/insider/themes/{{ $theme->id }}/approve" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block">Одобрить тему</button>
                        </form>

                        <form action="/insider/themes/{{ $theme->id }}/ban" method="POST" class="mb-2">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-block">Забанить тему</button>
                        </form>

                        <a href="/insider/users/{{ $theme->user->id }}/ban-themes" class="btn btn-warning btn-block"
                           onclick="return confirm('Забанить пользователя {{ $theme->user->name }}?')">Забанить автора</a>

                        <a href="/insider/themes/moderation" class="btn btn-secondary btn-block mt-2">Назад к списку</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
