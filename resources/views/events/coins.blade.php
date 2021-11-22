@extends('layouts.left-menu')

@section('content')
    <div class="row">
        <div class="col">
            <h2>Начисление бонуса за проведение события</h2>
            <div class="card">
                <div class="card-body">

                    <form method="POST">
                        @csrf

                        <strong>Организаторы</strong>
                        @foreach ($event->userOrgs as $user)
                            <div class="form-group">
                                <label>{{$user->name}}</label>
                                <input name="prize{{$user->id}}" type="number" class="form-control" value="15">
                                @if ($errors->has('prize'.$user->id))
                                    <span class=" help-block error-block">
                                <strong>{{ $errors->first('prize'.$user->id) }}</strong>
                                </span>
                                @endif
                            </div>
                        @endforeach

                        <strong>Участники</strong>
                        @foreach ($event->participants as $user)
                            @if (!$event->userOrgs->contains($user))
                                <div class="form-group">
                                    <label>{{$user->name}}</label>
                                    <input name="prize{{$user->id}}" type="number" class="form-control" value="5">
                                    @if ($errors->has('prize'.$user->id))
                                        <span class=" help-block error-block">
                                <strong>{{ $errors->first('prize'.$user->id) }}</strong>
                                </span>
                                    @endif
                                </div>
                            @endif
                        @endforeach

                        <div class="form-group">
                            <input type="submit" value="Добавить" class="btn btn-success form-control">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection