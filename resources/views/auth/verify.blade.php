@extends('layouts.left-menu')
@section('title', 'Подтверждение почты')
@section('content')
<div class="gc-form-layout">
    <div class="gc-card gc-form-sheet">
        <x-gc-page-header title="Подтвердите почту" description="Это нужно для продолжения работы на платформе." :embedded="true" />
        <p>Откройте письмо со ссылкой для подтверждения, отправленное на <strong>{{ Auth::user()->email }}</strong>.</p>
        <p class="gc-help-text">Если письма нет, проверьте папку «Спам» или запросите новую ссылку.</p>
        @if(session('resent'))<p class="alert alert-primary" role="status">Новая ссылка отправлена на вашу почту.</p>@endif
        <div class="gc-settings-connection-actions">
            <form method="POST" action="{{ route('verification.resend') }}">@csrf<button type="submit" class="btn btn-primary">Отправить ссылку ещё раз</button></form>
            <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-outline-secondary">Выйти</button></form>
        </div>
    </div>
</div>
@endsection
