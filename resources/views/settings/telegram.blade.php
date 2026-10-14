@extends('layouts.left-menu')

@section('title', 'Настройки Telegram')

@section('content')
<div class="container-xl px-0">
    @include('settings.partials.tabs')
    <div class="gc-card gc-page-header mb-4">
        <div>
            <span class="gc-eyebrow">Администрирование</span>
            <h2 class="fw-medium mb-1">Настройки Telegram</h2>
            <p class="text-muted mb-0">Бот для привязки профилей и отправки уведомлений.</p>
        </div>
        <button type="submit" form="telegram-settings-form" class="btn btn-success rounded-3 px-4 flex-shrink-0"><i class="fas fa-check me-1"></i> Сохранить</button>
    </div>

    @if (session('status'))
        <div class="alert alert-success rounded-3" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger rounded-3" role="alert">
            <div class="fw-medium mb-1">Проверьте настройки. Изменения не сохранены.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <div class="small mt-2">Новые секретные значения нужно ввести повторно.</div>
        </div>
    @endif

    <form id="telegram-settings-form" method="POST" action="{{ route('settings.telegram.update') }}" autocomplete="off">
        @csrf
        @method('PUT')
        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-6">
                <section class="gc-card p-3 p-md-4 h-100" aria-labelledby="telegram-bot-heading">
                    <h4 id="telegram-bot-heading" class="mb-3">Бот</h4>
                    <label for="telegram-username" class="form-label">Имя бота</label>
                    <div class="input-group">
                        <span class="input-group-text">@</span>
                        <input id="telegram-username" name="settings[bot_username]" type="text" class="form-control" maxlength="32" spellcheck="false"
                               value="{{ old('settings.bot_username', $settings['bot_username']) }}" placeholder="your_bot">
                    </div>
                    <div class="form-text mb-3">Используется в ссылке «Подключить Telegram» в профиле. Имя и ключ должны принадлежать одному боту.</div>
                    @include('settings.partials.telegram-secret', ['field' => 'bot_token', 'label' => 'Ключ бота', 'hint' => 'Ключ от @BotFather. Если очистить ключ, запросы к боту прекратятся.'])
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="gc-card p-3 p-md-4 h-100" aria-labelledby="telegram-proxy-heading">
                    <h4 id="telegram-proxy-heading" class="mb-3">Прокси</h4>
                    <p class="text-muted small">Применяется к отправке уведомлений и получению сообщений ботом.</p>
                    @include('settings.partials.telegram-secret', ['field' => 'proxy', 'label' => 'Адрес прокси', 'hint' => 'HTTP(S) или SOCKS5: схема://логин:пароль@хост:порт. Специальные символы в логине и пароле нужно URL-кодировать. MTProto-прокси не поддерживаются.'])
                </section>
            </div>
        </div>

        <details class="gc-card p-3 p-md-4 mb-4" @if($errors->has('settings.api_url') || $errors->has('settings.timeout') || $errors->has('secrets.webhook_secret.*')) open @endif>
            <summary class="fw-medium">Дополнительные параметры</summary>
            <div class="row g-3 mt-1">
                <div class="col-12 col-md-8">
                    <label for="telegram-api-url" class="form-label">Адрес Bot API</label>
                    <input id="telegram-api-url" name="settings[api_url]" type="url" class="form-control" maxlength="2048" required
                           value="{{ old('settings.api_url', $settings['api_url']) }}">
                    <div class="form-text">По умолчанию https://api.telegram.org. Базовый URL без ключа бота и названия метода.</div>
                </div>
                <div class="col-12 col-md-4">
                    <label for="telegram-timeout" class="form-label">Время ожидания, секунд</label>
                    <input id="telegram-timeout" name="settings[timeout]" type="number" class="form-control" min="1" max="120" required
                           value="{{ old('settings.timeout', $settings['timeout']) }}">
                </div>
                <div class="col-12">
                    @include('settings.partials.telegram-secret', ['field' => 'webhook_secret', 'label' => 'Секрет вебхука', 'hint' => 'Нужен только при подключении через вебхук. Укажите тот же секрет при регистрации вебхука в Telegram. Сохранение этой формы не регистрирует вебхук; пустой секрет отключает его проверку.'])
                </div>
            </div>
        </details>

        <div class="gc-card p-3 p-md-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="text-muted small">Изменения применяются к следующим запросам.<br>Ключи и адрес прокси хранятся в зашифрованном виде.</div>
            <button type="submit" class="btn btn-success rounded-3 px-4"><i class="fas fa-check me-1"></i> Сохранить настройки</button>
        </div>
    </form>

    <details class="gc-card p-3 p-md-4 mb-4">
        <summary class="fw-medium">Восстановить серверные настройки</summary>
        <p class="text-muted small mt-3">Удалить сохранённые здесь параметры Telegram и использовать конфигурацию сервера. Настройки ИИ сохранятся.</p>
        <form method="POST" action="{{ route('settings.telegram.reset') }}" onsubmit="return confirm('Восстановить серверные настройки Telegram? Сохранённые здесь ключи и параметры будут удалены.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger rounded-3">Восстановить настройки Telegram</button>
        </form>
    </details>
</div>
<script>
document.querySelectorAll('[data-secret-action]').forEach(function (select) {
    function sync() {
        var input = document.getElementById(select.dataset.secretAction);
        input.disabled = select.value !== 'replace';
        input.required = select.value === 'replace';
        if (input.disabled) input.value = '';
    }
    select.addEventListener('change', sync);
    sync();
});
</script>
@endsection
