@if(Auth::check() && Request::is('insider/courses') && \App\Support\ScreenPet::forUser(Auth::user()))
    <li><button class="dropdown-item" type="button" data-screen-pet-toggle aria-pressed="false"><i class="fas fa-paw me-2" aria-hidden="true"></i>Питомец на экране<span class="ms-2" data-screen-pet-check aria-hidden="true" hidden>✓</span></button></li>
@endif
