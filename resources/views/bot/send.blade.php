@extends('layouts.left-menu')

@section('title')
    GeekClass: Отправка сообщения
@endsection

@section('content')
    <h2>Отправка сообщения</h2>
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="POST" class="form-horizontal">
                        {{ csrf_field() }}

                        <div class="form-group">
                            <label for="message">Текст уведомления</label>

                            <textarea id="message" class="form-control" name="message">{{old('message')}}</textarea>
                            @if ($errors->has('message'))
                                <span class="help-block error-block">
                                        <strong>{{ $errors->first('message') }}</strong>
                                    </span>
                            @endif
                        </div>

                        <input type="submit" class="btn btn-success" value="Отправить"/>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
