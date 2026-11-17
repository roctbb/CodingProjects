@if(($isOwner ?? false) && \App\Support\ScreenPet::forUser(Auth::user()))
    <x-gc-form-section title="Интерфейс" description="Изменения сохраняются автоматически.">
        <x-gc-toggle name="screen_pet" label="Питомец на экране" :checked="true" hint="Экипированный питомец иногда гуляет по нижнему краю экрана." data-screen-pet-toggle disabled />
    </x-gc-form-section>
@endif
