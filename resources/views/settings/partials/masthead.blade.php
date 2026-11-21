<div class="gc-page-masthead mb-4">
    <x-gc-page-header :title="$title" :description="$description" embedded>
        <x-slot:breadcrumb><span class="gc-eyebrow">Администрирование</span></x-slot:breadcrumb>
    </x-gc-page-header>
    @include('settings.partials.tabs')
</div>
