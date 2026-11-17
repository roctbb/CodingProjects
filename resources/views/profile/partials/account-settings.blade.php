@if(session('oidc_error'))<div class="alert alert-danger" role="alert">{{ session('oidc_error') }}</div>@endif
@if(session('status'))<div class="alert alert-primary" role="status">{{ session('status') }}</div>@endif
<div class="gc-card gc-form-sheet">
    <x-gc-form-section title="Вход в аккаунт">
        <div class="gc-settings-value"><span class="gc-meta-label">Email для входа</span><span>{{ $user->email }}</span></div>
        @if(config('services.silaeder_oidc.enabled') && $isOwner && in_array($user->role,['student','teacher','admin'],true))
            <div class="gc-settings-connection">
                <div><h3 class="gc-section-title">ЛК Силаэдра</h3><p class="gc-help-text mb-0">{{ $user->oidc_subject ? 'Аккаунт привязан. Можно входить через личный кабинет.' : 'Вход через личный кабинет без локального пароля.' }}</p></div>
                @if($user->oidc_subject)<span class="gc-soft-badge">Подключено</span>@else<a class="btn btn-outline-secondary" href="{{ route('silaeder.link') }}">Привязать ЛК</a>@endif
            </div>
        @endif
    </x-gc-form-section>
    <x-gc-form-section title="Сменить пароль" description="Сохранение пароля не изменит данные профиля.">
        <x-gc-form-errors />
        <form id="account-password-form" method="POST" action="{{ url('/insider/profile/'.$user->id.'/password') }}" class="gc-form-stack" data-account-form>
            @csrf
            <input type="hidden" name="username" value="{{ $user->email }}" autocomplete="username">
            <div class="gc-settings-passwords">
                <x-gc-password-field label="Новый пароль" hint="От 6 до 72 символов." />
                <x-gc-password-field name="password_confirmation" id="password-confirm" label="Повторите пароль" confirm="password" />
            </div>
            <div class="gc-settings-actions"><span class="gc-help-text" data-form-state="account-password-form" aria-live="polite"></span><button type="submit" class="btn btn-primary">Сохранить пароль</button></div>
        </form>
    </x-gc-form-section>
    @if($isOwner)
        <x-gc-form-section title="Уведомления в Telegram">
            <div class="gc-settings-connection">
                <div><p class="mb-1">Учебные уведомления через бота</p><p class="gc-help-text mb-0">Telegram-контакт в профиле и подключение бота настраиваются отдельно.</p></div>
                <span class="gc-soft-badge">{{ $user->telegram_chat_id ? 'Подключено' : 'Не подключено' }}</span>
            </div>
            @if(app(\App\Services\TelegramSettings::class)->botUsername()!=='')
                <div class="gc-settings-connection-actions">
                    <a class="btn btn-outline-secondary" href="{{ url('/insider/profile/'.$user->id.'/telegram-link') }}">{{ $user->telegram_chat_id ? 'Переподключить Telegram' : 'Подключить Telegram' }}</a>
                    @if($user->telegram_chat_id)
                        <form method="POST" action="{{ url('/insider/profile/'.$user->id.'/telegram-unlink') }}">@csrf<button type="submit" class="btn btn-link text-muted" data-confirm="Отключить Telegram-уведомления?">Отключить</button></form>
                    @endif
                </div>
                <p class="gc-help-text mb-0">Откроется бот с одноразовой ссылкой. Нажмите Start для подключения.</p>
            @else
                <p class="gc-help-text mb-0">Telegram-бот пока не настроен администратором.</p>
                @if($user->telegram_chat_id)<form method="POST" action="{{ url('/insider/profile/'.$user->id.'/telegram-unlink') }}">@csrf<button type="submit" class="btn btn-outline-secondary" data-confirm="Отключить Telegram-уведомления?">Отключить Telegram</button></form>@endif
            @endif
        </x-gc-form-section>
    @endif
    @include('layouts.partials.screen-pet-toggle')
</div>
