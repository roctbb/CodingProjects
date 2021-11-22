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
    .form-signin .checkbox {
        font-weight: 400;
    }
    .form-signin .form-control {
        position: relative;
        box-sizing: border-box;
        height: auto;
        padding: 10px;
        font-size: 16px;
    }
    .form-signin .form-control:focus {
        z-index: 2;
    }
    .form-signin input[type="email"] {
        margin-bottom: -1px;
        border-bottom-right-radius: 0;
        border-bottom-left-radius: 0;
    }
    .form-signin input[type="password"] {
        margin-bottom: 10px;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }
</style>
@endsection

@section('content')
    <form class="form-signin" method="post">
        {{ csrf_field() }}
        <img style="height: 64px;" src="{{ url('images/icons/icons8-idea-64.png') }}">&nbsp;
        <h1 style="margin-top: 10px;" class="h3 mb-3 font-weight-normal">Активация внешнего сервиса</h1>
        <label for="code" class="sr-only">Код</label>
        <input type="text" id="code" name="code" class="form-control" placeholder="1234" required autofocus>
        @if ($errors->has('code'))
            <span class="help-block error-block">
                                        <strong>{{ $errors->first('code') }}</strong>
                                    </span>
        @endif
        <button style="margin-top: 10px;" class="btn btn-lg btn-primary btn-block" type="submit">Активировать</button>
    </form>
@endsection
