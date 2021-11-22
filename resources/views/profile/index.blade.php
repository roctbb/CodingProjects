@extends('layouts.left-menu')

@section('content')
    <div class="row" style="margin-top: 0;margin-left: 0;margin-right: 0;">

        <div class="card-deck">


            @foreach($users->sortByDesc(function ($user) {return $user->score();}) as $user)
                @if (!$user->is_hidden)
                    <div class="card" style="min-width: 340px;">
                        <div class="card-body">
                            <div class="row">
                                <div class="col" style="width: 100px; max-width: 100px;">
                                    @if ($user->image!=null)
                                        <div class="mr-3 rounded-circle img-circle" style='background-image: url("{{url('/media/'.$user->image)}}");'>
                                        </div>
                                    @else
                                        <div class="mr-3 rounded-circle img-circle" style='background-image: url("http://api.adorable.io/avatars/256/{{$user->id}}");'>
                                        </div>
                                    @endif
                                </div>
                                <div class="col-auto" style="width: calc(100% - 100px); max-width: calc(100% - 100px)">
                                    <h5 class="card-title"><a href="{{url('/insider/profile/'.$user->id)}}">{{ $user->name }}</a>
                                    </h5>
                                    <p><a tabindex="0" data-toggle="popover" data-trigger="focus" title="Ранги"
                                          data-html="true" data-content="{{\App\Rank::getRanksListHTML($user->rank())}}"><span
                                                    style="font-size: 15px;" class="badge badge-pill badge-success"><i
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

    <script>
        $(function () {
            $('[data-toggle="popover"]').popover()
        });
        $('.popover-dismiss').popover({
            trigger: 'focus'
        });
    </script>
@endsection