<x-gc-tab-strip class="mb-4">
<nav class="nav gc-page-tabs gap-2" aria-label="Разделы настроек">
    <a class="nav-link {{ Request::routeIs('settings.ai.edit') ? 'active' : '' }}" href="{{ route('settings.ai.edit') }}" @if(Request::routeIs('settings.ai.edit')) aria-current="page" @endif>
        <i class="fas fa-magic me-1"></i> ИИ
    </a>
    <a class="nav-link {{ Request::routeIs('settings.ai.usage') ? 'active' : '' }}" href="{{ route('settings.ai.usage') }}" @if(Request::routeIs('settings.ai.usage')) aria-current="page" @endif>
        <i class="fas fa-chart-column me-1" aria-hidden="true"></i> Расход ИИ
    </a>
    <a class="nav-link {{ Request::routeIs('settings.telegram.*') ? 'active' : '' }}" href="{{ route('settings.telegram.edit') }}" @if(Request::routeIs('settings.telegram.*')) aria-current="page" @endif>
        <i class="fab fa-telegram me-1"></i> Telegram
    </a>
    <a class="nav-link {{ Request::routeIs('settings.system-store.*') ? 'active' : '' }}" href="{{ route('settings.system-store.edit') }}" @if(Request::routeIs('settings.system-store.*')) aria-current="page" @endif>
        <i class="fas fa-coins me-1"></i> Цены и акции
    </a>
    <a class="nav-link {{ Request::routeIs('settings.stickers.*') ? 'active' : '' }}" href="{{ route('settings.stickers.index') }}" @if(Request::routeIs('settings.stickers.*')) aria-current="page" @endif><i class="fas fa-note-sticky me-1" aria-hidden="true"></i> Наклейки</a>
</nav>
</x-gc-tab-strip>
