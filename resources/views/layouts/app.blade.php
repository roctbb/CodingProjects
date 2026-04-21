@extends('layouts.full')

@section('title')
    @yield('title')
@overwrite

@section('head')
    @yield('head')
@overwrite

@section('content')
    <div class="geek-app-content-shell">
        @yield('content')
    </div>
@overwrite
