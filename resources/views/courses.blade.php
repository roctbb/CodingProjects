@extends('layouts.left-menu')

@section('title')
    GeekClass: курсы
@endsection

@section('content')
    <div class="row" style="margin-top: 15px; margin-bottom: 15px;">
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

                    <li class="nav-item" style="margin-left: 5px;">
                        <a class="btn btn-success btn-sm nav-link" style="color: white;"
                           href="{{url('/categories/create/')}}"><i
                                    class="icon ion-plus-round" style="color: white;"></i>&nbsp;Создать</a>
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
                            <div class="card mb-3" style="min-width: 280px;">
                                <img alt="Team" class="card-img-top" src="{{$category->card_image_url}}">
                                <div class="card-body" style="background-color: rgba(255,255,255,0.9);">
                                    <h5 style="font-weight: 300;"
                                        class="card-title">
                                        <a href="{{url('categories/'.$category->id)}}"
                                           style="color: #333741;">{{$category->title}}</a>
                                    </h5>
                                    <p class="card-text"
                                       style="font-size: 0.8rem;">{{$category->short_description}}</p>

                                </div>
                            </div>
                        @endforeach

                    </div>

                </div>
                @if (Auth::check() and Auth::user()->role=='admin')
                    <div class="tab-pane fade show" id="draft" role="tabpanel" aria-labelledby="active">
                        <div class="card-deck">
                            @foreach($categories->where('is_available', false) as $category)
                                <div class="card mb-3" style="min-width: 280px; max-width: 33%;">
                                    <img alt="Team" class="card-img-top" src="{{$category->card_image_url}}">
                                    <div class="card-body" style="background-color: rgba(255,255,255,0.9);">
                                        <h5 style="font-weight: 300;"
                                            class="card-title">
                                            <a href="{{url('categories/'.$category->id)}}"
                                               style="color: #333741;">{{$category->title}}</a>
                                        </h5>
                                        <p class="card-text"
                                           style="font-size: 0.8rem;">{{$category->short_description}}</p>

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
