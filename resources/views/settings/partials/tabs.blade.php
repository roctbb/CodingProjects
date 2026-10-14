<nav class="nav nav-pills gap-2 mb-4" aria-label="Разделы настроек">
    <a class="nav-link {{ Request::routeIs('settings.ai.*') ? 'active' : '' }}" href="{{ route('settings.ai.edit') }}" @if(Request::routeIs('settings.ai.*')) aria-current="page" @endif>
        <i class="fas fa-magic me-1"></i> ИИ
    </a>
    <a class="nav-link {{ Request::routeIs('settings.telegram.*') ? 'active' : '' }}" href="{{ route('settings.telegram.edit') }}" @if(Request::routeIs('settings.telegram.*')) aria-current="page" @endif>
        <i class="fab fa-telegram me-1"></i> Telegram
    </a>
</nav>
