@extends('layouts.left-menu')

@section('title')
    {{ $user->name }}
@endsection

@section('content')
    <div class="row mx-0 mt-0">

        <div class="card-deck">


            @foreach($users->sortByDesc(function ($user) {return $user->score();}) as $user)
                @if (!$user->is_hidden)
                    <div class="card profile-card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-auto">
                                    <div class="mr-3 rounded-circle img-circle" data-background-image="{{ $user->imageUrl() }}">
                                    </div>
                                </div>
                                <div class="col">
                                    <h5 class="card-title"><a href="{{url('/insider/profile/'.$user->id)}}">{{ $user->name }}</a>
                                    </h5>
                                    <p><a tabindex="0" data-toggle="popover" data-trigger="focus" title="Ранги"
                                           data-html="true" data-content="{{\App\Rank::getRanksListHTML($user->rank())}}"><span
                                                     class="badge badge-pill badge-success small"><i
                                                        class="icon ion-ios-arrow-up"></i> {{$user->rank()->name}}</span></a>
                                        <br>

                                        @if ($user->is_trainee)
                                            <span class="badge badge-pill badge-info">Стажер</span>
                                        @endif
                                        @if ($user->is_teacher)
                                            <span class="badge badge-pill badge-info">Преподаватель</span>
                                        @endif</p>
                                </div>
                            </div>

                        </div>
                    </div>






                @endif
            @endforeach
        </div>
    </div>
@endsection
