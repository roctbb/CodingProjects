@if (Auth::check() && Auth::user()->role === 'admin')
    <li>
        <a class="gc-sidebar__link {{ Request::is('insider/settings*') ? 'active' : '' }}" href="{{ route('settings.ai.edit') }}">
            <i class="fas fa-sliders-h"></i> Настройки
        </a>
    </li>
@endif
