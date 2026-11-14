@php
    $canSeeEmail = ($isOwner || in_array($guest->role, ['teacher', 'admin'])) && filled($user->email);
@endphp
@if($canSeeEmail || filled($user->telegram) || filled($user->git))
<section id="profile-contacts" class="{{ ($embedded ?? false) ? 'gc-profile-hero__section gc-profile-hero__contacts' : 'gc-card p-3' }}">
    <h2 class="gc-section-title">Контакты</h2>
    <dl class="gc-facts gc-contact-grid mb-0">
        @if($canSeeEmail)<x-gc-contact label="Почта" icon="far fa-envelope" :value="$user->email" :href="'mailto:'.$user->email" />@endif
        @if(filled($user->telegram))<x-gc-contact label="Telegram" icon="fab fa-telegram" :value="\App\Support\ContactLinks::label($user->telegram, true)" :href="\App\Support\ContactLinks::telegram($user->telegram)" external />@endif
        @if(filled($user->git))<x-gc-contact label="Git" icon="fas fa-code-branch" :value="\App\Support\ContactLinks::label($user->git)" :href="\App\Support\ContactLinks::web($user->git)" external />@endif
    </dl>
</section>
@endif
