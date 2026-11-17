@if (Auth::check() && Auth::user()->role === 'admin')
    <li>
        <a class="dropdown-item {{ Request::is('insider/settings*') ? 'active' : '' }}" href="{{ route('settings.ai.edit') }}">
            <i class="fas fa-sliders-h me-2"></i>Настройки
        </a>
    </li>
@endif
