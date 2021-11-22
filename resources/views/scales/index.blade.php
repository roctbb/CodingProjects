@extends('layouts.left-menu')

@section('title')
    GeekClass
@endsection

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Шкалы образовательных результатов</h2>
        </div>
        <div class="col">
            <a class="float-right btn btn-success btn-sm" href="{{url('/insider/scales/create/')}}"><i
                        class="icon ion-plus-round" style="color: white;"></i>&nbsp;Добавить шкалу</a>
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">

        @foreach($scales as $scale)

            <div class="col-6">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-9">
                                <h5 style="padding: 0; margin: 0;" class="card-title">{{$scale->name}}</h5>

                            </div>
                            <div class="col-md-3">
                                <a href="{{url('insider/scales/'.$scale->id)}}"
                                   class="btn btn-success btn-sm  float-right">Подробнее</a>
                            </div>
                        </div>


                    </div>

                </div>
            </div>

        @endforeach
    </div>



@endsection
