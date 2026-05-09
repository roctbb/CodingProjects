@if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
    @php
        $sessionAlertType = str_replace('alert-', '', Session::get('alert-class', 'info'));
        $sessionAlertType = in_array($sessionAlertType, ['success', 'warning', 'danger', 'info']) ? $sessionAlertType : 'info';
        $sessionAlertIcon = [
            'success' => 'fas fa-check',
            'warning' => 'fas fa-exclamation',
            'danger' => 'fas fa-times',
            'info' => 'fas fa-info',
        ][$sessionAlertType];
    @endphp
    <div id="gcSessionAlert" class="gc-session-alert gc-session-alert--{{ $sessionAlertType }} gc-card gc-alert-row alert alert-dismissible fade show" role="alert">
        <span class="gc-session-alert__icon">
            <i class="{{ $sessionAlertIcon }}"></i>
        </span>
        <div class="gc-session-alert__content min-width-0 flex-grow-1">
            @if(Session::get('alert-title'))
                <strong>{{ Session::get('alert-title') }}</strong>
            @endif
            {{ Session::get('alert-text') }}
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" data-bs-target="#gcSessionAlert" aria-label="Закрыть"></button>
    </div>
@endif
