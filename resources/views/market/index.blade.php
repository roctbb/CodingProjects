@extends('layouts.left-menu')

@section('title')
    Магазин
@endsection

@section('content')
    @php
        $availableGoods = $goods->where('number', '>', 0)->count();
    @endphp

    <div class="gx-market-shell cp-market-page">
        <section class="card border-0 rounded-4 shadow-sm gx-market-hero">
            <div class="card-body">
                <div class="gx-market-hero-top">
                    <div>
                        <h1 class="gx-market-title">Магазин</h1>
                        <p class="gx-market-description">Обменивайте монеты на призы, материалы и полезные бонусы.</p>
                    </div>
                    <div class="gx-market-stats">
                        <span class="badge gx-market-stat-badge">
                            Баланс: <img src="{{ url('images/icons/icons8-coins-48.png') }}" class="gx-market-coin-icon" alt="coins"> {{ $user->balance() }}
                        </span>
                        <span class="badge gx-market-stat-badge">Товаров: {{ $goods->count() }}</span>
                        <span class="badge gx-market-stat-badge">В наличии: {{ $availableGoods }}</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="gx-market-toolbar ge-page-header">
            <div class="gx-market-toolbar-head">
                <h4 class="gx-market-toolbar-title">Каталог товаров</h4>
                <p class="gx-market-toolbar-subtitle">Выберите товар и оформите покупку за монеты</p>
            </div>
            @if ($user->role == 'admin')
                <div class="gx-market-toolbar-actions">
                    <a class="btn btn-outline-secondary btn-sm" href="{{url('/insider/market/orders')}}">Заказы</a>
                    <a class="btn btn-primary btn-sm gx-market-create-btn" href="{{url('/insider/market/create/')}}">
                        <i class="icon fa-solid fa-circle-plus"></i>&nbsp;Добавить
                    </a>
                </div>
            @endif
        </div>

        @if($goods->count())
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-4 gx-market-grid">
                @foreach($goods as $good)
                    <div class="col">
                        <article class="card h-100 gx-market-card {{ $good->number <= 0 ? 'is-soldout' : '' }}">
                            <div class="gx-market-image-wrap">
                                @if ($good->image)
                                    <img class="card-img-top gx-market-image" src="{{$good->image}}" alt="{{ $good->name }}"/>
                                @else
                                    <img class="card-img-top gx-market-image" src="{{ url('images/clip-education.png') }}" alt="{{ $good->name }}"/>
                                @endif
                            </div>

                            <div class="card-body gx-market-card-body d-flex flex-column">
                                <div class="gx-market-status-line">
                                    @if ($good->number > 0)
                                        <span class="badge text-bg-success">В наличии: {{$good->number}}x</span>
                                    @else
                                        <span class="badge text-bg-danger">Закончился</span>
                                    @endif
                                    @if ($user->role == 'teacher' || $user->role == 'admin')
                                        <a href="{{url('/insider/market/'.$good->id.'/edit')}}" class="btn btn-primary btn-sm gx-market-edit-btn">
                                            <i class="icon fa-solid fa-pen-to-square"></i>
                                        </a>
                                    @endif
                                </div>

                                <h5 class="card-title gx-market-card-title">{{$good->name}}</h5>
                                <p class="card-text gx-market-card-description">{{$good->description}}</p>

                                @if ($good->number > 0 and $good->price <= $user->balance())
                                    <a href="{{url('/insider/market/'.$good->id.'/buy')}}" class="btn btn-primary gx-market-buy-btn mt-auto"
                                       onclick="return confirm('Вы уверены?')">
                                        Купить за {{$good->price}}
                                        <img class="cp-h-20" src="{{ url('images/icons/icons8-coins-48.png') }}" alt="coins"/>
                                    </a>
                                @else
                                    <button type="button" class="btn btn-primary gx-market-buy-btn mt-auto" disabled>
                                        Купить за {{$good->price}}
                                        <img class="cp-h-20" src="{{ url('images/icons/icons8-coins-48.png') }}" alt="coins"/>
                                    </button>
                                @endif
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        @else
            <div class="alert alert-secondary gx-market-empty" role="alert">
                Пока нет доступных товаров.
            </div>
        @endif

        @if ($user->role == 'admin')
            <div class="gx-market-archive-toolbar ge-page-header">
                <div class="gx-market-toolbar-head">
                    <h4 class="gx-market-toolbar-title">Архив товаров</h4>
                    <p class="gx-market-toolbar-subtitle">Снятые с продажи позиции</p>
                </div>
            </div>

            @if($archive->count())
                <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 row-cols-xxl-4 gx-market-grid gx-market-grid--archive">
                    @foreach($archive as $good)
                        <div class="col">
                            <article class="card h-100 gx-market-card is-archived">
                                <div class="gx-market-image-wrap">
                                    @if ($good->image)
                                        <img class="card-img-top gx-market-image" src="{{$good->image}}" alt="{{ $good->name }}"/>
                                    @else
                                        <img class="card-img-top gx-market-image" src="{{ url('images/clip-education.png') }}" alt="{{ $good->name }}"/>
                                    @endif
                                </div>

                                <div class="card-body gx-market-card-body d-flex flex-column">
                                    <div class="gx-market-status-line">
                                        <span class="badge text-bg-secondary">Снят с продажи</span>
                                        <a href="{{url('/insider/market/'.$good->id.'/edit')}}" class="btn btn-primary btn-sm gx-market-edit-btn">
                                            <i class="icon fa-solid fa-pen-to-square"></i>
                                        </a>
                                    </div>
                                    <h5 class="card-title gx-market-card-title">{{$good->name}}</h5>
                                    <p class="card-text gx-market-card-description">{{$good->description}}</p>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="alert alert-secondary gx-market-empty" role="alert">
                    Архив пока пуст.
                </div>
            @endif
        @endif
    </div>

@endsection
