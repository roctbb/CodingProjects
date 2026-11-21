@extends('layouts.left-menu')
@section('title', 'Настройки ИИ')
@section('content')
<div class="gc-form-layout">
    <div class="gc-page-masthead mb-4">
        <x-gc-page-header title="Настройки ИИ" description="Одно подключение и две модели для всей платформы." embedded />
        @include('settings.partials.tabs')
    </div>
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if($legacyGatewayDisabled)<div class="alert alert-info">Старый протокол шлюза отключён. Укажите адрес и ключ OpenAI-совместимого API. Старые ключи шлюза не переносятся автоматически.</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Изменения не сохранены. Проверьте поля.</strong>
            <ul class="mb-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <span class="small">Новые ключи и адреса прокси нужно ввести повторно.</span>
        </div>
    @endif
    <form id="ai-settings-form" class="gc-card p-3 p-md-4 mb-4" method="POST" action="{{ route('settings.ai.update') }}" autocomplete="off" data-ai-settings data-account-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="settings[shared_models]" value="1">
        <x-gc-form-section title="Подключение">
            <input type="hidden" name="settings[provider]" value="openai" data-ai-provider-select>
            <p class="gc-help-text mb-0">Общий шлюз GeekClass для помощника, проверки решений, текста и изображений.</p>
            @foreach($providers as $provider => $label)
                @php
                    $connection = $settings['providers'][$provider];
                    $base = $connection['base_url'] ?? \App\Services\Ai\Connection::legacyBaseUrl($connection);
                    $imageApi = old('settings.providers.'.$provider.'.image_api', $connection['image_api'] ?? 'responses');
                @endphp
                <fieldset class="gc-stack border-0 p-0 m-0" data-ai-provider="{{ $provider }}">
                    <legend class="visually-hidden">Параметры {{ $label }}</legend>
                        <x-gc-field :name="'settings[providers]['.$provider.'][base_url]'" :id="'ai-base-'.$provider" label="Базовый адрес API" hint="Укажите адрес со служебным префиксом, например https://vpn.telepat.online/gpt/v1. Путь операции добавляется автоматически.">
                            <input id="ai-base-{{ $provider }}" name="settings[providers][{{ $provider }}][base_url]" type="url" class="form-control" maxlength="2048" value="{{ old('settings.providers.'.$provider.'.base_url', $base) }}" placeholder="https://api.example.com/v1" spellcheck="false" required>
                        </x-gc-field>
                        <x-gc-field :name="'settings[providers]['.$provider.'][image_api]'" :id="'ai-image-api-'.$provider" label="API для изображений">
                            <select id="ai-image-api-{{ $provider }}" name="settings[providers][{{ $provider }}][image_api]" class="form-select" data-ai-image-api>
                                <option value="images" @selected($imageApi === 'images')>Images</option>
                                <option value="responses" @selected($imageApi === 'responses')>Responses</option>
                            </select>
                            <p class="gc-help-text mb-0">Для текста всегда используется Responses. Ключ и прокси общие для обоих API.</p>
                        </x-gc-field>
                    <div class="row g-3">
                        @foreach(['key' => 'API-ключ', 'proxy' => 'Сетевой прокси'] as $field => $fieldLabel)
                            <div class="col-12 col-md-6">
                                <label for="ai-{{ $provider }}-{{ $field }}-action" class="form-label">{{ $fieldLabel }} <span class="gc-help-text">· {{ $configured[$provider][$field] ? 'задан' : 'не задан' }}</span></label>
                                <select id="ai-{{ $provider }}-{{ $field }}-action" name="secrets[{{ $provider }}][{{ $field }}][action]" class="form-select" data-secret-action="ai-{{ $provider }}-{{ $field }}-value">
                                    <option value="keep">{{ $configured[$provider][$field] ? 'Оставить текущий' : 'Оставить незаданным' }}</option>
                                    <option value="replace">{{ $configured[$provider][$field] ? 'Заменить' : 'Указать' }}</option>
                                    <option value="clear">{{ $field === 'proxy' ? 'Без прокси' : 'Удалить ключ' }}</option>
                                    <option value="inherit">Использовать серверную настройку</option>
                                </select>
                                <label for="ai-{{ $provider }}-{{ $field }}-value" class="visually-hidden">{{ $label }}: новый {{ $fieldLabel }}</label>
                                <input id="ai-{{ $provider }}-{{ $field }}-value" name="secrets[{{ $provider }}][{{ $field }}][value]" type="password" class="form-control mt-2" autocomplete="new-password" maxlength="4096" placeholder="{{ $field === 'key' ? 'Новый API-ключ' : 'socks5h://логин:пароль@хост:порт' }}" data-secret-value disabled hidden>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </x-gc-form-section>
        <x-gc-form-section title="Модели">
            <div class="row g-3">
                @foreach(['text' => 'Для текста', 'image' => 'Для изображений'] as $type => $label)
                    <div class="col-12 col-md-6">
                        <x-gc-field :name="'settings[defaults]['.$type.'][model]'" :id="'ai-model-'.$type" :label="$label">
                            <input id="ai-model-{{ $type }}" name="settings[defaults][{{ $type }}][model]" type="text" class="form-control" value="{{ old('settings.defaults.'.$type.'.model', $settings['defaults'][$type]['model']) }}" maxlength="200" spellcheck="false" required>
                            <p class="gc-help-text mb-0" data-ai-model-hint="{{ $type }}"></p>
                        </x-gc-field>
                        <x-gc-field :name="'settings[defaults]['.$type.'][timeout]'" :id="'ai-timeout-'.$type" label="Ожидание ответа, секунд" class="mt-3">
                            <input id="ai-timeout-{{ $type }}" name="settings[defaults][{{ $type }}][timeout]" type="number" class="form-control" value="{{ old('settings.defaults.'.$type.'.timeout', $settings['defaults'][$type]['timeout']) }}" min="1" max="180" required>
                        </x-gc-field>
                    </div>
                @endforeach
            </div>
            @if($hasCommandOverrides)<p class="gc-help-text mb-0">Сохранение заменит прежние настройки отдельных операций этими двумя моделями.</p>@endif
            <div class="row g-3 mt-1">
                <div class="col-12 col-md-6">
                    <x-gc-field name="settings[defaults][recheck][model]" id="ai-model-recheck" label="Модель для повторной ИИ-проверки">
                        <input id="ai-model-recheck" name="settings[defaults][recheck][model]" type="text" class="form-control" value="{{ old('settings.defaults.recheck.model', $settings['defaults']['recheck']['model'] ?? '') }}" maxlength="200" spellcheck="false">
                        <p class="gc-help-text mb-0">Преподаватель может выбрать её при повторной проверке решения, которое уже проверял ИИ. Пустое поле отключает этот вариант. Проверка по тестам использует тесты задачи.</p>
                    </x-gc-field>
                </div>
                <div class="col-12 col-md-6">
                    <x-gc-field name="settings[defaults][recheck][timeout]" id="ai-timeout-recheck" label="Ожидание повторной ИИ-проверки, секунд">
                        <input id="ai-timeout-recheck" name="settings[defaults][recheck][timeout]" type="number" class="form-control" value="{{ old('settings.defaults.recheck.timeout', $settings['defaults']['recheck']['timeout'] ?? 180) }}" min="1" max="180" required>
                    </x-gc-field>
                </div>
            </div>
        </x-gc-form-section>
        <x-gc-form-section title="Ростислоп Нейрославович">
            <div class="row g-3" id="tutor-limits">
                <div class="col-12 col-md-6">
                    <x-gc-field name="settings[tutor_limits][platform_monthly_tokens]" id="tutor-platform_monthly_tokens" :label="$tutorLimits['platform_monthly_tokens']" hint="Включает вопросы учеников, ответы помощника и проверку ответов. Лимит обновляется в начале календарного месяца.">
                        <input id="tutor-platform_monthly_tokens" name="settings[tutor_limits][platform_monthly_tokens]" type="number" class="form-control" min="0" max="2147483647" required value="{{ old('settings.tutor_limits.platform_monthly_tokens', $settings['tutor_limits']['platform_monthly_tokens']) }}">
                    </x-gc-field>
                </div>
            </div>
            <details @if($errors->has('settings.tutor_limits.*')) open @endif>
                <summary>Дневные лимиты</summary>
                <div class="row g-3 mt-1">
                    @foreach($tutorLimits as $key => $label)
                        @continue($key === 'platform_monthly_tokens')
                        <div class="col-12 col-md-6">
                            <x-gc-field :name="'settings[tutor_limits]['.$key.']'" :id="'tutor-'.$key" :label="$label">
                                <input id="tutor-{{ $key }}" name="settings[tutor_limits][{{ $key }}]" type="number" min="0" max="2147483647" required class="form-control" value="{{ old('settings.tutor_limits.'.$key, $settings['tutor_limits'][$key]) }}">
                            </x-gc-field>
                        </div>
                    @endforeach
                </div>
            </details>
        </x-gc-form-section>
        <p class="gc-help-text mb-0">Изменения применяются со следующего запроса. Ключи и прокси хранятся зашифрованными.</p>
    </form>
    <details class="gc-card p-3 p-md-4 mb-4">
        <summary class="fw-medium">Восстановить серверные настройки</summary>
        <p class="gc-help-text mt-3">Удалить переопределения этого раздела и вернуться к конфигурации сервера.</p>
        <form method="POST" action="{{ route('settings.ai.reset') }}" data-confirm="Восстановить серверные настройки ИИ? Сохранённые здесь ключи и параметры будут удалены.">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger">Восстановить настройки</button>
        </form>
    </details>
    <x-gc-form-actions form="ai-settings-form" />
</div>
@endsection
