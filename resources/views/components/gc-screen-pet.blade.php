@php
    $screenPet = Auth::check() && Request::is('insider/courses')
        ? \App\Support\ScreenPet::forUser(Auth::user()) : null;
    if ($screenPet) {
        // Preview is restricted to the local synthetic QA server.
        $screenPet['preview'] = PHP_SAPI === 'cli-server' && getenv('UI_QA_MODE') === '1' && request()->boolean('pet_test');
    }
@endphp
@if($screenPet)
    <div class="gc-screen-pet" data-screen-pet='@json($screenPet)' hidden aria-hidden="true"></div>
@endif
