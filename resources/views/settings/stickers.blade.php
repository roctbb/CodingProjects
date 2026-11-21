@extends('layouts.left-menu')
@section('title', 'Каталог наклеек')
@section('content')
<div class="gc-form-layout">
    @include('settings.partials.masthead', ['title' => 'Каталог наклеек', 'description' => 'Изображения и коллекции для уроков.'])
    @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
<div class="gc-stack">
<section class="gc-card p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="h5 mb-0">{{ $editing ? 'Редактирование наклейки' : 'Новая наклейка' }}</h2>
        @if($editing)<a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.stickers.index') }}">Новая наклейка</a>@endif
    </div>
    <form method="POST" action="{{ $editing ? route('settings.stickers.update', $editing) : route('settings.stickers.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3 align-items-end">
            @if($editing)<div class="col-auto"><img src="{{ url($editing->image) }}" width="64" height="64" class="object-fit-contain" alt=""></div>
            @else<div class="col-md"><label for="image" class="form-label">Ссылка на изображение</label>
            <input id="image" name="image" class="form-control" type="url" value="{{ old('image') }}" required maxlength="255"></div>@endif
            <div class="col-md"><label for="name" class="form-label">Название наклейки</label>
            <input id="name" name="name" class="form-control" value="{{ old('name', $editing?->name) }}" maxlength="160" required></div>
            <div class="col-md"><label for="collection" class="form-label">Коллекция</label>
            <input id="collection" name="collection" class="form-control" list="collections" value="{{ old('collection', $editing?->collection) }}" maxlength="100" required>
            <datalist id="collections">@foreach($groups->keys() as $label)<option value="{{ $label }}"></option>@endforeach</datalist></div>
            <div class="col-auto"><button class="btn btn-primary">{{ $editing ? 'Сохранить' : 'Добавить' }}</button></div>
        </div>
        @if($errors->any())<ul class="text-danger mt-2" role="alert">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    </form>
</section>
@foreach($groups as $name => $items)
<section class="gc-card p-3 p-md-4"><h2 class="h5">{{ $name }} <small class="gc-help-text">{{ $items->count() }}</small></h2>
    <div class="gc-sticker-directory">
        @foreach($items as $item)
        <a class="gc-sticker-entry" @if($editing?->id === $item->id) aria-current="true" @endif href="{{ route('settings.stickers.index', ['edit' => $item->id]) }}">
            <img src="{{ url($item->image) }}" width="40" height="40" alt="" loading="lazy"><span>{{ $item->name }}</span>
        </a>
        @endforeach
    </div>
</section>
@endforeach
</div>
</div>
@endsection
