@extends('layouts.left-menu')

@section('title')
    Курсы
@endsection

@section('content')
    <div class="row my-3">
        <div class="col">
            <h2>Образовательные направления</h2>
        </div>
        <div class="col">
            @if (Auth::check() and Auth::user()->role=='admin')
                <ul class="nav nav-tabs nav-fill" id="coursesTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="active-tab" data-toggle="tab" href="#active" role="tab"
                           aria-controls="active" aria-selected="true">Активные</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="draft-tab" data-toggle="tab" href="#draft" role="tab"
                           aria-controls="draft" aria-selected="false">Черновики</a>
                    </li>

                    <li class="nav-item ml-1">
                        <a class="btn btn-success btn-sm nav-link text-white"
                           href="{{url('/categories/create/')}}"><i
                                    class="icon ion-plus-round"></i>&nbsp;Создать</a>
                    </li>

                </ul>
            @endif
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="tab-content" id="courses">
                <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active">


                    <div class="card-deck">
                        @foreach($categories->where('is_available', true) as $category)
                            <div class="card mb-3 category-card">
                                <img alt="Team" class="card-img-top" src="{{$category->card_image_url}}">
                                <div class="card-body market-card-body">
                                    <h5 class="card-title font-weight-light">
                                        <a href="{{url('categories/'.$category->id)}}"
                                           class="text-dark">{{$category->title}}</a>
                                    </h5>
                                    <p class="card-text small">{{$category->short_description}}</p>

                                </div>
                            </div>
                        @endforeach

                    </div>

                </div>
                @if (Auth::check() and Auth::user()->role=='admin')
                    <div class="tab-pane fade show" id="draft" role="tabpanel" aria-labelledby="active">
                        <div class="card-deck">
                            @foreach($categories->where('is_available', false) as $category)
                                <div class="card mb-3 category-card-draft">
                                    <img alt="Team" class="card-img-top" src="{{$category->card_image_url}}">
                                    <div class="card-body market-card-body">
                                        <h5 class="card-title font-weight-light">
                                            <a href="{{url('categories/'.$category->id)}}"
                                               class="text-dark">{{$category->title}}</a>
                                        </h5>
                                        <p class="card-text small">{{$category->short_description}}</p>

                                    </div>
                                </div>
                            @endforeach

                        </div>

                    </div>
                @endif
            </div>
        </div>
    </div>



@endsection
