@extends('layouts.left-menu')

@section('title')
    GeekClass: "{{$article->name}}"
@endsection
@section('content')
    <div class="row">
        <div class="col-12">
            @if ($article->image)
                <div class="jumbotron p-4 p-md-5 text-white rounded bg-dark"
                     style='background-size: contain;background-image: url("{{url($article->image)}}"); margin-bottom: 15px;'>
                    <div class="col-md-6 px-0">
                        @foreach($article->tags as $tag)
                            @if (\Auth::check())
                                <span class="badge badge-secondary badge-light">
                                <a target="_blank"
                                   href="{{url('/insider/articles?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                            @else
                                <span class="badge badge-secondary badge-light">
                                <a target="_blank" href="{{url('/articles?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                            @endif
                        @endforeach
                        <h1 class="display-4" style="color: white;">{{$article->name}}</h1>
                    </div>
                </div>
            @else
                @foreach($article->tags as $tag)
                    <span class="badge badge-secondary badge-light"><a target="_blank"
                                                                       href="{{url('/articles?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                @endforeach
                <h1 class="display-4">{{$article->name}}</h1>
            @endif
        </div>
    </div>

    @if (\Auth::check() && (\Auth::User()->role == 'admin' || \Auth::User()->id == $article->author->id))
        <div class="row">
            @if (\Auth::User()->role == 'admin')
                <div class="col-12" style="margin-right: 15px;">
                    <a href="{{url('/insider/articles/'.$article->id.'/delete')}}"
                       class="float-right"
                       style="margin-right: 5px;margin-left: 5px;"><i class="icon ion-close-round"></i> удалить</a>
                    <a href="{{url('/insider/articles/'.$article->id.'/edit')}}"
                       class="float-right"
                       style="margin-right: 5px;margin-left: 5px;"><i class="icon ion-edit"></i> изменить</a>

                </div>
            @endif
        </div>
    @endif


    <div class="row" style="margin-top: 15px;" id="root">
        <div class="col-12">
            <div style="float: left; width: 100%; padding-left: 5px;" class="col-auto">
                <div class="row">

                    <div class="col-1" style="width: 85px; max-width: 85px;">

                        @if ($article->author->image!=null)
                            <div class="mr-3 rounded-circle img-circle"
                                 style='background-image: url("{{url('/media/'.$article->author->image)}}");'>
                            </div>
                        @else
                            <div class="mr-3 rounded-circle img-circle"
                                 style='background-image: url("http://api.adorable.io/avatars/256/{{$article->author->id}}");'>
                            </div>
                        @endif
                    </div>
                    <div class="col-auto" style="width: calc(100% - 100px); max-width: calc(100% - 100px)">
                        @if (\Auth::check())
                            <h5>
                                <a href="{{url('/insider/profile/'.$article->author->id)}}">{{ $article->author->name }}</a>
                            </h5>
                        @else
                            <h5>{{ $article->author->name }}</h5>
                        @endif
                        <p><a tabindex="0" data-toggle="popover" data-trigger="focus" title="Ранги"
                              data-html="true"
                              data-content="{{\App\Rank::getRanksListHTML($article->author->rank())}}"><span
                                        style="font-size: 13px;" class="badge badge-pill badge-success"><i
                                            class="icon ion-ios-arrow-up"></i> {{$article->author->rank()->name}}</span></a>

                            @if ($article->author->is_trainee)
                                <span style="font-size: 13px;" class="badge badge-pill badge-info">Стажер</span>
                            @endif
                            @if ($article->author->is_teacher)
                                <span style="font-size: 13px;"
                                      class="badge badge-pill badge-info">Преподаватель</span>
                            @endif</p>
                    </div>
                </div>
                <div style="margin-top: 15px;" class="markdown markdown-big">
                    @parsedown($article->anounce)
                </div>
                <hr>
                <div class="row">
                    <div style="margin-top: 15px;" class="col-md-12 markdown markdown-big">
                        @parsedown($article->text)
                    </div>
                </div>
                <hr>
                <div class="card">
                    <div style="padding:10px" class="card-body">
                        @if(\Auth::check())
                            <gk-votes 
                            :upvotes="{{$article->getUpvotes()-$article->hasUpvoted(\Auth::id())}}"
                            :downvotes="{{$article->getDownvotes()-$article->hasDownvoted(\Auth::id())}}"
                            :upvoted="{{$article->hasUpvoted(\Auth::id())?'1':'0'}}"
                            :downvoted="{{$article->hasDownvoted(\Auth::id())?'1':'0'}}"
                            :canvote="true"
                            :urls="{upvote: '/insider/articles/{{$article->id}}/upvote',
                                downvote: '/insider/articles/{{$article->id}}/downvote'}"
                                sideways="true"
                                ></gk-votes>
                        @endif  
                        {{$article->comments->count()}}
                        <i class="material-icons small">message</i>

                    </div>
                </div>
            </div>
        </div>
    </div>
    @foreach($article->comments as $comment)
        <div class="row">
            <div class="col">
                <div class="row">
                    <ul class="avatars ">
                        <li>
                            <a href="{{ url('insider/profile/'.$comment->user->id) }}" data-toggle="tooltip"
                               title="Kenny">
                                @if ($comment->user->image!=null)
                                    <img alt="Image" src="{{url('/media/'.$comment->user->image)}}" class="avatar"/>
                                @else
                                    <img alt="Image" src="http://api.adorable.io/avatars/256/{{$comment->user->id}}"
                                         class="avatar"/>
                                @endif
                            </a>
                        </li>
                    </ul>
                    <div class="col">
                        <div class="d-flex justify-content-between">
                            <p class="text-secondary">{{$comment->user->name}}</p>
                            @if(\Auth::check())
                                @if(\Auth::id() == $comment->user->id || \Auth::user()->role == "admin")
                                    <a onclick="return confirm('Вы уверены?')" class="btn btn-round"
                                       href="/insider/articles/comments/{{$comment->id}}/delete">
                                        <i class="material-icons">close</i>
                                    </a>
                                    {{-- <a class="nav-link btn btn-danger"
                                    style="padding: 8px 9px;height: 40px; margin: 0 0; margin-left: 5px; width: 40px;"

                                    onclick="return confirm('Вы уверены?')"><i
                                                class="ion-close-round"></i></a> --}}
                                @endif
                            @endif
                        </div>
                        <blockquote>{!!parsedown(clean($comment->comment))!!}</blockquote>
                    </div>
                </div>
                <hr>
            </div>
        </div>


        <script src="{{asset('js/forum.js')}}"></script>
    @endforeach
    @if (\Auth::check())
        <form action="/insider/articles/{{$article->id}}/comment" method="POST">
            @csrf

            <div id="comment">
                <h5 style="margin-top: 10px;"><label for="title">Комментарий:</label></h5>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <div>
                            @foreach ($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif
                <textarea class="form-control" id="commentArea" rows="10" type="text"
                          name="comment">{{old('comment')}}</textarea>
                <input class="btn btn-primary" type="submit" style="margin:10px 0;" value="Оставить комментарий">
            </div>
        </form>
    @endif


    <script>
        var simplemde_text = new EasyMDE({
            spellChecker: false,
            autosave: true,
            element: document.getElementById("description")
        });
    </script>


@endsection
