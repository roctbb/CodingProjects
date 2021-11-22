@extends('layouts.left-menu')

@section('content')
    <div class="row" style="margin-top: 15px;">
        <div class="col">
            <h2>Статьи</h2>
        </div>
        <div class="col">
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">

        <div class="col-12">
            @if ($tag != "")
                <p style="margin-bottom: 5px; margin-top: 5px;">Показаны результаты по тэгу
                    <strong>{{ $tag->name }}</strong>. <a href="{{'/articles'}}">Все статьи</a>.</p>
            @endif
        </div>
    </div>
    <div class="row" style="margin-top: 15px;">

        @foreach ($articles as $article)

            <div class="col-md-12">
                <div class="row no-gutters border rounded overflow-hidden flex-md-row mb-4 shadow-sm h-md-250 position-relative">
                    <div class="col p-4 d-flex flex-column position-static">
                        <strong class="d-inline-block mb-2 text-primary">
                            @foreach($article->tags as $tag)
                                <span class="badge badge-secondary badge-light"><a
                                            href="{{url('/articles?tag='.$tag->name)}}">{{$tag->name}}</a></span>
                            @endforeach
                        </strong>


                        <h3 class="mb-0"><a href="{{url('/articles/'.$article->id)}}"
                                            style="color: #212529;">{{$article->name}}</a></h3>
                        <div class="mb-1 text-muted"
                             style="margin-top: 5px;">{{$article->created_at->format('H:i d.m.Y')}},@if (\Auth::check())
                                <a
                                        href="{{url('/insider/profile/'.$article->author->id)}}">{{ $article->author->name }}</a>@else {{ $article->author->name }} @endif
                        </div>
                        <div class="card-text mb-auto" style="margin-top: 15px;">@parsedown($article->anounce)</div>

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
                        <li class="page-item @if ($page==$i) active @endif"><a class="page-link"
                                                                               href="{{url('/articles?tag='.$tag->name.'&page='.$i)}}">{{$i}}</a>
                        </li>
                    @else
                        <li class="page-item @if ($page==$i) active @endif"><a class="page-link"
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



@endsection
