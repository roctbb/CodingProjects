@extends('layouts.empty-nc')

@section('head')
    <style>

        html,
        body {
            height: 100%;
        }

        body {
            display: -ms-flexbox;
            display: flex;
            -ms-flex-align: center;
            align-items: center;
            padding-top: 40px;
            padding-bottom: 40px;
            background-color: #f5f5f5;
        }

        .form-signin {
            width: 100%;
            max-width: 330px;
            padding: 15px;
            margin: auto;
        }

    </style>
@endsection

@section('content')
    <div class="form-signin">
        <img style="height: 64px;" src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;
        <h1 style="margin-top: 10px;" class="h3 mb-3 font-weight-normal">Активация внешнего сервиса прошла успешно!<br><br>
            <small>Можно начинать пользоваться!</small>
        </h1>
    </div>
@endsection
