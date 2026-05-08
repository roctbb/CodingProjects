@extends('layouts.left-menu')

@section('title', 'Магазин')

@section('content')
    <div class="courses-page-heading market-page-heading mb-3">
        <div class="min-width-0">
            <h2 class="mb-1">Магазин</h2>
            <div class="courses-inline-stats">
                <span>
                    <img src="{{ url('images/icons/icons8-coins-48.png') }}" height="18" alt="">
                    <strong>{{ $user->balance() }}</strong> GC
                </span>
                <span><strong>{{ $goods->count() }}</strong> товаров</span>
            </div>
        </div>
        @if ($user->role == 'admin')
            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a class="btn btn-outline-secondary btn-sm" href="{{ url('/insider/market/orders') }}">Заказы</a>
                <a class="btn btn-success courses-create-btn" href="{{ url('/insider/market/create/') }}"><i class="fas fa-plus me-1"></i>Добавить</a>
            </div>
        @endif
    </div>

    <div class="courses-card-grid market-grid">
        @foreach($goods as $good)
            <div class="course-index-card gc-card market-card" style="--card-accent: hsl({{ $good->id * 37 % 360 }}, 65%, 55%)">
                    @if ($good->image)
                        <img class="card-img-top market-card__image" src="{{ $good->image }}" alt="{{ $good->name }}">
                    @else
                        <div class="market-card__image market-card__image--empty">
                            <i class="fas fa-gift"></i>
                        </div>
                    @endif
                    <div class="course-index-card__body">
                        <div class="market-card__status mb-2">
                            @if ($good->number > 0)
                                <span class="badge rounded-pill course-status-pill">В наличии: {{ $good->number }}</span>
                            @else
                                <span class="badge rounded-pill course-status-pill course-status-pill--warning">Закончился</span>
                            @endif
                        </div>
                        <h6 class="course-index-card__title mb-0">{{ $good->name }}</h6>
                        <p class="course-index-card__description market-card__description">{{ $good->description }}</p>

                        <div class="market-card__footer mt-2">
                            @if ($good->number > 0 && $good->price <= $user->balance())
                                <a href="{{ url('/insider/market/'.$good->id.'/buy') }}" class="btn btn-primary btn-sm market-buy-btn" data-confirm="Вы уверены?">
                                    Купить за {{ $good->price }} <img src="{{ url('images/icons/icons8-coins-48.png') }}" height="14" alt="">
                                </a>
                            @else
                                <button class="btn btn-primary btn-sm market-buy-btn" disabled>{{ $good->price }} <img src="{{ url('images/icons/icons8-coins-48.png') }}" height="14" alt=""></button>
                            @endif
                            @if ($user->role == 'teacher' || $user->role == 'admin')
                                <a href="{{ url('/insider/market/'.$good->id.'/edit') }}" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fas fa-edit"></i></a>
                            @endif
                        </div>
                    </div>
            </div>
        @endforeach
    </div>

    @if ($user->role == 'admin' && $archive->count())
        <h6 class="text-muted text-uppercase small fw-bold mt-4 mb-2">Архив</h6>
        <div class="courses-card-grid courses-card-grid--compact market-grid--archive">
            @foreach($archive as $good)
                    <div class="course-index-card gc-card market-card opacity-75" style="--card-accent: hsl({{ $good->id * 37 % 360 }}, 30%, 55%)">
                        <div class="course-index-card__body">
                            <h6 class="course-index-card__title mb-0">{{ $good->name }}</h6>
                            <p class="course-index-card__description">{{ $good->description }}</p>
                            <span class="badge rounded-pill course-status-pill course-status-pill--muted">Снят с продажи</span>
                            <a href="{{ url('/insider/market/'.$good->id.'/edit') }}" class="btn btn-outline-secondary btn-sm float-end"><i class="fas fa-edit"></i></a>
                        </div>
                    </div>
            @endforeach
        </div>
    @endif
@endsection
