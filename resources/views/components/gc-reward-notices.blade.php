@if(Auth::check() && Auth::user()->role === 'student' && Request::is('insider*'))
    <div class="gc-reward-notices" data-reward-notices data-endpoint="{{ route('rewards.claim') }}" data-profile-url="{{ url('/insider/profile/'.Auth::id()) }}" aria-live="polite" aria-atomic="true"></div>
@endif
