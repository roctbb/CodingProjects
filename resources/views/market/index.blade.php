@extends('layouts.left-menu')

@section('title')
    Магазин
@endsection

@section('content')
    <div class="row mt-3">
        <div class="col">
            <h2> Магазин</h2>
            <p>Ваш баланс - <img src="{{ url('images/icons/icons8-coins-48.png') }}"
                                  height="30" alt=""/>&nbsp;{{$user->balance()}}&nbsp;</p>
        </div>
        <div class="col">
            @if ($user->role=='admin')
                <a class="btn btn-secondary float-right ml-2" href="{{url('/insider/market/orders')}}">Заказы</a>
                <a class="btn btn-round float-right" href="{{url('/insider/market/create/')}}"><i
                            class="fas fa-plus"></i></a>
            @endif
        </div>
    </div>
    <div class="row mt-3">
        <div class="card-deck">

            @foreach($goods as $good)
                <div class="card market-card">
                    @if ($good->image != null)
                        <img class="card-img-top" src="{{$good->image}}" alt=""/>
                    @endif

                    <div class="card-body market-card-body">
                        @if ($good->number > 0)
                            <p><span class="badge badge-success">В наличии: {{$good->number}}x</span></p>
                        @else
                            <p><span class="badge badge-danger">Закончился</span></p>
                        @endif
                        <h5 class="card-title font-weight-light mt-3">{{$good->name}} @if ($user->role=='teacher' || $user->role=='admin')
                                <span class="float-right"><a
                                            href="{{url('/insider/market/'.$good->id.'/edit')}}"
                                            class="btn btn-primary btn-sm"><i
                                                class="icon ion-android-create"></i></a></span>
                            @endif </h5>
                        <p class="card-text small">{{$good->description}}</p>

                        @if ($good->number > 0 and $good->price <= $user->balance())
                            <a href="{{url('/insider/market/'.$good->id.'/buy')}}" class="btn btn-primary"
                                data-confirm="Вы уверены?">Купить за {{$good->price}} <img
                                        src="{{ url('images/icons/icons8-coins-48.png') }}" height="20" alt=""/></a>
                        @else
                            <button type="button" class="btn btn-primary" disabled>Купить за {{$good->price}} <img
                                        src="{{ url('images/icons/icons8-coins-48.png') }}" height="20" alt=""/></button>
                        @endif


                    </div>

                </div>
            @endforeach
        </div>

    </div>
    @if ($user->role=='admin')

        <div class="row mt-3">
            <div class="col">
                <h2> Архив</h2>
            </div>
        </div>
        <div class="row mt-3">
            <div class="card-deck">

                @foreach($archive as $good)
                    <div class="card market-card-archive">

                        <div class="card-body market-card-body">
                            @if ($good->image != null)
                                <img src="{{$good->image}}" class="mt-3" width="200" alt=""/>
                            @endif
                            <h5 class="card-title font-weight-light mt-3">{{$good->name}} <span class="float-right"><a
                                            href="{{url('/insider/market/'.$good->id.'/edit')}}"
                                            class="btn btn-primary btn-sm"><i
                                                class="icon ion-android-create"></i></a></span></h5>
                            <p class="card-text small">{{$good->description}}</p>
                            <p><span class="badge badge-secondary">Снят с продажи</span></p>


                        </div>

                    </div>
                @endforeach
            </div>

        </div>
    @endif

@endsection
