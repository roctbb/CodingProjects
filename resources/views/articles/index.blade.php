@extends('layouts.left-menu')

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Статьи</h2>
        </div>
        <div class="col">
            <ul class="nav nav-tabs nav-fill" id="articlesTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" id="all-tab" data-toggle="tab" href="#all" role="tab"
                       aria-controls="all" aria-selected="true">Все статьи</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="mine-tab" data-toggle="tab" href="#mine" role="tab"
                       aria-controls="mine" aria-selected="false">Мои статьи</a>
                </li>
                @if ($user->role == 'admin')
                    <li class="nav-item">
                        <a class="nav-link" id="draft-tab" data-toggle="tab" href="#draft" role="tab"
                           aria-controls="draft" aria-selected="false">Ожидают утверждения</a>
                    </li>
                @endif
                <li class="nav-item" style="margin-left: 5px;">
                    <a class="btn btn-success btn-sm nav-link" style="color: white;"
                       href="{{url('/insider/articles/create/')}}"><i
                                class="icon ion-plus-round" style="color: white;"></i>&nbsp;Добавить</a>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content" id="articles" style="margin-top: 15px;">
        <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all">
            <div class="row" style="margin-top: 15px;">

                <div class="col-12">
                    @if ($tag != "")
                        <p style="margin-bottom: 5px; margin-top: 5px;">Показаны результаты по тэгу
                            <strong>{{ $tag->name }}</strong>. <a href="{{'/articles'}}">Все статьи</a>.</p>
                    @endif
                </div>

                @foreach ($articles as $article)

                    <div class="col-md-12">
                        <div class="row no-gutters border rounded overflow-hidden flex-md-row mb-4 shadow-sm h-md-250 position-relative">
                            <div class="col p-4 d-flex flex-column position-static">
                                <strong class="d-inline-block mb-2 text-primary">
                                    @foreach($article->tags as $сtag)
                                        <span class="badge badge-secondary badge-light"><a
                                                    href="{{url('/articles?tag='.$сtag->name)}}">{{$сtag->name}}</a></span>
                                    @endforeach
                                </strong>


                                <h3 class="mb-0"><a href="{{url('/articles/'.$article->id)}}"
                                                    style="color: #212529;">{{$article->name}}</a></h3>
                                <div class="mb-1 text-muted"
                                     style="margin-top: 5px;">{{$article->created_at->format('H:i d.m.Y')}},@if (\Auth::check()) <a
                                            href="{{url('/insider/profile/'.$article->author->id)}}">{{ $article->author->name }}</a>@else {{ $article->author->name }} @endif
                                </div>
                                <div class="card-text mb-auto" style="margin-top: 15px;">@parsedown($article->anounce)
                                </div>

                                <a href="{{url('/articles/'.$article->id)}}" style="margin-top: 10px;">Читать
                                    полностью...</a>
                            </div>

                            @if ($article->image)

                                <div class="col-auto d-none d-lg-block"
                                     style='width: 20%; background-size: cover;background-image: url("{{$article->image}}")'>

                                </div>
                            @endif
                        </div>
                    </div>



                @endforeach

                <nav class="col-md-12 justify-content-center" style="text-align: center;">
                    <ul class="pagination">
                        @if ($page > 1)
                            <li class="page-item">
                                <a class="page-link" href="{{url('/articles?page='.($page-1))}}" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        @endif
                        @for($i = 1; $i <= $pages; $i++)
                            @if ($tag)
                                <li class="page-item @if ($page==$i) current @endif"><a class="page-link"
                                                                                        href="{{url('/articles?tag='.$tag->name.'&page='.$i)}}">{{$i}}</a>
                                </li>
                            @else
                                <li class="page-item @if ($page==$i) current @endif"><a class="page-link"
                                                                                        href="{{url('/articles?page='.$i)}}">{{$i}}</a>
                                </li>
                            @endif
                        @endfor
                        @if ($page < $pages)
                            <li class="page-item">
                                <a class="page-link" href="{{url('/articles?page='.($page+1))}}" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                </nav>
            </div>
        </div>

        <div class="tab-pane fade" id="mine" role="tabpanel" aria-labelledby="mine">
            <div class="row" style="margin-top: 15px;">
                @if ($my_articles->count() == 0)
                    <div class="jumbotron">
                        <h1 class="display-4">Время журналистики!</h1>
                        <p class="lead" style="margin-top: 15px;">Техническая статья или мастер-класс это отличный
                            способ не только поделиться своим опытом с миром, но еще и лучше разобраться в теме. Самое
                            время попробовать себя в этом!</p>
                        <a class="btn btn-primary btn-lg" style="margin-top: 15px;"
                           href="{{url('/insider/articles/create/')}}"
                           role="button">Написать статью</a>
                    </div>
                @endif

                @foreach ($my_articles as $article)

                    <div class="col-md-12">
                        <div class="row no-gutters border rounded overflow-hidden flex-md-row mb-4 shadow-sm h-md-250 position-relative">
                            <div class="col p-4 d-flex flex-column position-static">

                                <div class="row">
                                    <div class="col col-md-auto">
                                        <strong class="d-inline-block mb-2 text-primary">
                                            @foreach($article->tags as $tag)
                                                <span class="badge badge-secondary badge-light"><a
                                                            href="{{url('/articles?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                                            @endforeach
                                        </strong>


                                        <h3 class="mb-0"><a href="{{url('/articles/'.$article->id)}}"
                                                            style="color: #212529;">{{$article->name}}</a></h3>
                                    </div>
                                    <div class="col">
                                        @if ($user->id = $article->author->id)
                                            <div class="float-right">
                                                @if ($article->is_approved && !$article->is_draft)
                                                    <a href="#"
                                                       class="btn btn-primary btn-sm disabled">Опубликовано</a>
                                                @else
                                                    @if ($user->role == 'admin')
                                                        <a href="{{url('/insider/articles/'.$article->id.'/publish')}}"
                                                           class="btn btn-primary btn-sm">Опубликовать</a>
                                                    @else
                                                        @if ($article->is_draft)
                                                            <a href="{{url('/insider/articles/'.$article->id.'/ask_review')}}"
                                                               class="btn btn-primary btn-sm">Попросить опубликовать</a>
                                                        @else
                                                            <a href="#"
                                                               class="btn btn-primary btn-sm disabled">Ожидает
                                                                публикации</a>
                                                        @endif
                                                    @endif
                                                    <a href="{{url('/insider/articles/'.$article->id.'/edit')}}"
                                                       class="btn btn-success btn-sm"><i
                                                                class="icon ion-android-create"></i></a>
                                                    @if (!$article->is_approved)
                                                        <a href="{{url('/insider/articles/'.$article->id.'/delete')}}"
                                                           class="btn btn-danger btn-sm"
                                                           onclick="return confirm('Вы уверены?')"><i
                                                                    class="ion-close-round"></i></a>
                                                    @endif
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>


                                <div class="mb-1 text-muted"
                                     style="margin-top: 5px;">{{$article->created_at->format('H:i d.m.Y')}}
                                    ,@if (\Auth::check()) <a
                                            href="{{url('/insider/profile/'.$article->author->id)}}">{{ $article->author->name }}</a>@else {{ $article->author->name }} @endif
                                </div>
                                <div class="card-text mb-auto" style="margin-top: 15px;">@parsedown($article->anounce)
                                </div>

                                <a href="{{url('/articles/'.$article->id)}}" style="margin-top: 10px;">Читать
                                    полностью...</a>
                            </div>

                            @if ($article->image)

                                <div class="col-auto d-none d-lg-block"
                                     style='width: 20%; background-size: cover;background-image: url("{{$article->image}}")'>

                                </div>
                            @endif
                        </div>
                    </div>



                @endforeach
            </div>

        </div>

        @if ($user->role == 'admin')
            <div class="tab-pane fade" id="draft" role="tabpanel" aria-labelledby="draft">
                <div class="row" style="margin-top: 15px;">
                    @if ($draft_articles->count() == 0)
                        <div class="col-12">
                            <p>Кажется, что все уже утверждено...ну или просто пока не написано.</p>
                        </div>
                    @endif
                    @foreach ($draft_articles as $article)

                        <div class="col-md-12">
                            <div class="row no-gutters border rounded overflow-hidden flex-md-row mb-4 shadow-sm h-md-250 position-relative">
                                <div class="col p-4 d-flex flex-column position-static">
                                    <div class="row">
                                        <div class="col col-md-auto">
                                            <strong class="d-inline-block mb-2 text-primary">
                                                @foreach($article->tags as $tag)
                                                    <span class="badge badge-secondary badge-light"><a
                                                                href="{{url('/articles?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                                                @endforeach
                                            </strong>


                                            <h3 class="mb-0"><a href="{{url('/articles/'.$article->id)}}"
                                                                style="color: #212529;">{{$article->name}}</a></h3>
                                        </div>
                                        <div class="col">
                                            @if ($user->id = $article->author->id)
                                                <div class="float-right">
                                                    <a href="{{url('/insider/articles/'.$article->id.'/publish')}}"
                                                       class="btn btn-primary btn-sm">Опубликовать</a>
                                                    <a href="{{url('/insider/articles/'.$article->id.'/draft')}}"
                                                       class="btn btn-primary btn-sm">Отправить на доработку</a>

                                                    <a href="{{url('/insider/articles/'.$article->id.'/edit')}}"
                                                       class="btn btn-success btn-sm"><i
                                                                class="icon ion-android-create"></i></a>

                                                    <a href="{{url('/insider/articles/'.$article->id.'/delete')}}"
                                                       class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Вы уверены?')"><i
                                                                class="ion-close-round"></i></a>

                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="mb-1 text-muted"
                                         style="margin-top: 5px;">{{$article->created_at->format('H:i d.m.Y')}}
                                        ,@if (\Auth::check()) <a
                                                href="{{url('/insider/profile/'.$article->author->id)}}">{{ $article->author->name }}</a>@else {{ $article->author->name }} @endif
                                    </div>
                                    <div class="card-text mb-auto" style="margin-top: 15px;">
                                        @parsedown($article->anounce)
                                    </div>

                                    <a href="{{url('/articles/'.$article->id)}}" style="margin-top: 10px;">Читать
                                        полностью...</a>
                                </div>

                                @if ($article->image)

                                    <div class="col-auto d-none d-lg-block"
                                         style='width: 20%; background-size: cover;background-image: url("{{$article->image}}")'>

                                    </div>
                                @endif
                            </div>
                        </div>



                    @endforeach
                </div>
            </div>
        @endif

    </div>

@endsection
