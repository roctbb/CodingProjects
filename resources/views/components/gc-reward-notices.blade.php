@if(Auth::check() && in_array(Auth::user()->role, ['student', 'teacher', 'admin'], true) && Request::is('insider*'))
    <div class="gc-reward-notices" data-reward-notices data-endpoint="{{ route('rewards.claim') }}" data-profile-url="{{ url('/insider/profile/'.Auth::id()) }}" aria-live="polite" aria-atomic="true"></div>
@endif
