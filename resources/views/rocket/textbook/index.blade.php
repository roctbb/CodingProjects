@extends('rocket.layouts.top')

@section('title')
    {{ $textbook->name }}
@endsection

@section('style')
    <style>
        a .card-body {
            color: #4A5073;
        }
    </style>
@endsection

@section('content')
    <!-- Hero -->
    <section class="section-header bg-primary pb-7 pb-lg-7 text-white">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-10 text-center">
                    <h1 class="mb-3">{{ $textbook->name }}</h1>
                    <p class="lead px-lg-5 mb-5">{{ $textbook->description }}</p>
                </div>
            </div>
        </div>
        <div class="pattern bottom"></div>
    </section>
    <section class="section section-md pt-6 bg-white">
        <div class="container">
            @foreach($textbook->chapters as $chapter)
                <div class="row">
                    <div class="col">
                        <h2 class="h4 mb-4">{{ $chapter->name }}</h2>
                    </div>
                </div>
                <div class="row justify-content-center">
                    @foreach($chapter->lessons as $key => $lesson)
                        <div class="col-12 col-lg-6 mb-3">
                            <a href="{{ url('/textbook/'.$textbook->id.'/lesson/'.$lesson->id) }}" class="card border-light animate-up-3 shadow-soft p-0 p-lg-1">
                                <div class="card-body">
                                    <h5 class="mb-4">{{ $key + 1 }}. {{ $lesson->name }}</h5>
                                    <div class="text-gray">
                                        <p class="text-gray mb-4">@parsedown($lesson->description)</p>
                                    </div>
                                    <!--<div class="d-flex align-items-center">
                                        <div class="avatar-md">
                                            <img class="rounded-circle" src="../assets/img/team/profile-picture-1.jpg" alt="avatar">
                                        </div>
                                        <div class="small text-gray ml-3">
                                            <div><span>Updated 2 days ago</span></div>
                                            <div>Written by&nbsp;<strong>Richard Thomas</strong></div>
                                        </div>
                                    </div>-->
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
    <section class="section section-lg bg-soft pb-5">
        <div class="container">
            <div class="row">
                <div class="col">
                    <div class="text-center">
                        <h3 class="mb-4">Can't find what you are looking for? <br class="d-sm-none"> Let us know!</h3>
                        <a class="text-primary font-weight-normal h4" href="./contact.html">Drop us a line <span
                                    class="icon icon-sm icon-primary ml-1"><i class="fas fa-arrow-right"></i></span> </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
