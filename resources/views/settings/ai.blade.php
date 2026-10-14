@extends('layouts.left-menu')

@section('title', 'Настройки ИИ')

@section('content')
<div class="container-xl px-0">
    <div class="gc-card gc-page-header mb-4">
        <div>
            <span class="gc-eyebrow">Администрирование</span>
            <h2 class="fw-medium mb-1">Настройки ИИ</h2>
            <p class="text-muted mb-0">Подключения и модели для всех операций платформы.</p>
        </div>
        <button type="submit" form="ai-settings-form" class="btn btn-success rounded-3 px-4 flex-shrink-0"><i class="fas fa-check me-1"></i> Сохранить</button>
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
            <div class="small mt-2">Новые ключи и адреса прокси нужно ввести повторно.</div>
        </div>
    @endif

    <form id="ai-settings-form" method="POST" action="{{ route('settings.ai.update') }}" autocomplete="off">
        @csrf
        @method('PUT')
        <section class="gc-card p-3 p-md-4 mb-4" aria-labelledby="ai-defaults-heading">
            <h4 id="ai-defaults-heading" class="mb-2">По умолчанию</h4>
            <p class="text-muted small mb-3">Эти параметры применяются, когда для операции не выбраны отдельные значения.</p>
            <div class="row g-3">
                <div class="col-12 col-lg-4">
                    <label for="ai-provider" class="form-label">Основное подключение</label>
                    <select id="ai-provider" name="settings[provider]" class="form-select" required>
                        @foreach ($providers as $provider => $label)
                            <option value="{{ $provider }}" @selected(old('settings.provider', $settings['provider']) === $provider)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @foreach (['text' => 'Текст', 'image' => 'Изображения'] as $type => $label)
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="ai-model-{{ $type }}" class="form-label">{{ $label }}: модель</label>
                        <input id="ai-model-{{ $type }}" name="settings[defaults][{{ $type }}][model]" type="text" class="form-control"
                               value="{{ old('settings.defaults.'.$type.'.model', $settings['defaults'][$type]['model']) }}" maxlength="200" spellcheck="false" required>
                        <label for="ai-timeout-{{ $type }}" class="form-label small mt-2">Время ожидания, секунд</label>
                        <input id="ai-timeout-{{ $type }}" name="settings[defaults][{{ $type }}][timeout]" type="number" class="form-control"
                               value="{{ old('settings.defaults.'.$type.'.timeout', $settings['defaults'][$type]['timeout']) }}" min="1" max="180" required>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="row g-4 mb-4">
            @foreach ($providers as $provider => $label)
                <div class="col-12 col-xl-6">
                    <section class="gc-card p-3 p-md-4 h-100" aria-labelledby="ai-heading-{{ $provider }}">
                        <h4 id="ai-heading-{{ $provider }}" class="mb-2">{{ $label }}</h4>
                        <p class="text-muted small">{{ $provider === 'gateway' ? 'Запросы через ваш шлюз.' : 'Прямые запросы в OpenAI или совместимый Responses API.' }}</p>
                        <label for="ai-endpoint-{{ $provider }}" class="form-label">Эндпоинт</label>
                        <input id="ai-endpoint-{{ $provider }}" name="settings[providers][{{ $provider }}][endpoint]" type="url" class="form-control"
                               value="{{ old('settings.providers.'.$provider.'.endpoint', $settings['providers'][$provider]['endpoint']) }}" maxlength="2048" required>
                        <div class="form-text mb-3">Полный URL, включая <code>/v1/responses</code>.</div>

                        @foreach (['key' => 'API-ключ', 'proxy' => 'Сетевой прокси'] as $field => $fieldLabel)
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <label for="ai-{{ $provider }}-{{ $field }}-action" class="form-label mb-0">{{ $fieldLabel }}</label>
                                    <span class="badge {{ $configured[$provider][$field] ? 'bg-success' : 'bg-secondary' }}">{{ $configured[$provider][$field] ? 'Задан' : 'Не задан' }}</span>
                                </div>
                                <select id="ai-{{ $provider }}-{{ $field }}-action" name="secrets[{{ $provider }}][{{ $field }}][action]" class="form-select" data-secret-action="ai-{{ $provider }}-{{ $field }}-value">
                                    <option value="keep">Оставить текущий</option>
                                    <option value="replace">Заменить</option>
                                    <option value="clear">{{ $field === 'proxy' ? 'Без прокси' : 'Удалить ключ' }}</option>
                                    <option value="inherit">Использовать серверную настройку</option>
                                </select>
                                <label for="ai-{{ $provider }}-{{ $field }}-value" class="visually-hidden">{{ $label }}: новый {{ $fieldLabel }}</label>
                                <input id="ai-{{ $provider }}-{{ $field }}-value" name="secrets[{{ $provider }}][{{ $field }}][value]" type="password" class="form-control mt-2"
                                       autocomplete="new-password" maxlength="4096" placeholder="{{ $field === 'key' ? 'Введите новый API-ключ' : 'socks5h://логин:пароль@хост:порт' }}" data-secret-value disabled>
                                <div class="form-text">
                                    {{ $field === 'key' ? 'Сохранённый ключ не отображается. Замена выполняется только при выборе «Заменить».' : 'HTTP(S) или SOCKS5. Логин и пароль указываются в URL; специальные символы нужно URL-кодировать.' }}
                                </div>
                            </div>
                        @endforeach
                    </section>
                </div>
            @endforeach
        </div>

        <section class="gc-card p-3 p-md-4 mb-4" aria-labelledby="ai-commands-heading">
            <h4 id="ai-commands-heading" class="mb-2">Настройки операций</h4>
            <p class="text-muted small mb-3">Пустые поля наследуют общие значения. Для изображений укажите модель Responses, которая поддерживает генерацию картинок.</p>
            @foreach ($commands as $command => $label)
                <fieldset class="{{ !$loop->first ? 'border-top pt-3 mt-3' : '' }}">
                    <legend class="fs-6 fw-medium mb-2">{{ $label }}</legend>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label for="ai-command-{{ $command }}-provider" class="form-label small">Подключение</label>
                            <select id="ai-command-{{ $command }}-provider" name="settings[commands][{{ $command }}][provider]" class="form-select">
                                <option value="">По умолчанию</option>
                                @foreach ($providers as $provider => $providerLabel)
                                    <option value="{{ $provider }}" @selected(old('settings.commands.'.$command.'.provider', $settings['commands'][$command]['provider'] ?? '') === $provider)>{{ $providerLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="ai-command-{{ $command }}-model" class="form-label small">Модель</label>
                            <input id="ai-command-{{ $command }}-model" name="settings[commands][{{ $command }}][model]" type="text" class="form-control" maxlength="200" spellcheck="false"
                                   placeholder="По умолчанию" value="{{ old('settings.commands.'.$command.'.model', $settings['commands'][$command]['model'] ?? '') }}">
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="ai-command-{{ $command }}-timeout" class="form-label small">Ожидание, секунд</label>
                            <input id="ai-command-{{ $command }}-timeout" name="settings[commands][{{ $command }}][timeout]" type="number" class="form-control" min="1" max="180"
                                   placeholder="По умолчанию" value="{{ old('settings.commands.'.$command.'.timeout', $settings['commands'][$command]['timeout'] ?? '') }}">
                        </div>
                    </div>
                </fieldset>
            @endforeach
        </section>

        <div class="gc-card p-3 p-md-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="text-muted small">Изменения применяются к следующим запросам.<br>Ключи и адреса прокси хранятся в зашифрованном виде.</div>
            <button type="submit" class="btn btn-success rounded-3 px-4"><i class="fas fa-check me-1"></i> Сохранить настройки</button>
        </div>
    </form>

    <details class="gc-card p-3 p-md-4 mb-4">
        <summary class="fw-medium">Восстановить серверные настройки</summary>
        <p class="text-muted small mt-3">Удалить изменения, внесённые в этом разделе, и снова использовать значения из конфигурации сервера.</p>
        <form method="POST" action="{{ route('settings.ai.reset') }}" onsubmit="return confirm('Восстановить серверные настройки ИИ? Сохранённые здесь ключи и параметры будут удалены.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger rounded-3">Восстановить настройки</button>
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
