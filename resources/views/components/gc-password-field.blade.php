@props(['name' => 'password', 'id' => null, 'label' => 'Пароль', 'autocomplete' => 'new-password', 'minlength' => 6, 'maxlength' => 72, 'hint' => null, 'confirm' => null])
@php
    $inputId = $id ?? $name;
@endphp
<x-gc-field :name="$name" :id="$inputId" :label="$label" :hint="$hint">
    <div class="gc-password-control">
        <input id="{{ $inputId }}" type="password" name="{{ $name }}" class="form-control" autocomplete="{{ $autocomplete }}" @if($minlength) minlength="{{ $minlength }}" @endif @if($maxlength) maxlength="{{ $maxlength }}" @endif @if($confirm) data-password-confirm="{{ $confirm }}" @endif required>
        <button type="button" class="gc-password-toggle" data-password-target="{{ $inputId }}" aria-controls="{{ $inputId }}" aria-label="Показать пароль: {{ $label }}" aria-pressed="false"><i class="far fa-eye" aria-hidden="true"></i></button>
    </div>
</x-gc-field>
