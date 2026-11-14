@props(['title', 'description' => null, 'back', 'backLabel' => 'Назад', 'form', 'submit' => 'Сохранить'])
<div class="gc-form-layout gc-authoring-layout" data-authoring-page>
    <div class="gc-page-masthead mb-4">
        <x-gc-page-header :title="$title" :description="$description">
            <x-slot:breadcrumb><a href="{{ $back }}">← {{ $backLabel }}</a></x-slot:breadcrumb>
        </x-gc-page-header>
    </div>
    <x-gc-form-errors />
    <div class="gc-authoring-body">
        {{ $slot }}
        <x-gc-form-actions :form="$form" :label="$submit" :cancel="$back" />
    </div>
    @isset($after){{ $after }}@endisset
</div>
