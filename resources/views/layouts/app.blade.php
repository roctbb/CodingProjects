@extends('layouts/full')

@section('head')


    @yield('head')    
@overwrite

@section('content')
    <div class="mx-auto col-md-9 col-11" style="margin-top: 15px">
        @yield('content')
    </div>
@overwrite