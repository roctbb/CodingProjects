@extends('layouts/full')

@section('title')
    @yield('title')
@overwrite

@section('head')


    @yield('head')    
@overwrite

@section('content')
    <div class="mx-auto col-md-9 col-11 mt-3">
        @yield('content')
    </div>
@overwrite
