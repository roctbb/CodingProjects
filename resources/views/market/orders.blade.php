@extends('layouts.left-menu')
@section('title', 'Заказы магазина')
@section('content')
    <div class="gc-page-masthead mb-4">
        <x-gc-page-header title="Заказы магазина" description="Выдача товаров и история доставок.">
            <x-slot:breadcrumb><a href="{{ url('/insider/market#market-orders') }}">← В магазин</a></x-slot:breadcrumb>
        </x-gc-page-header>
    </div>
    @include('market.partials.order-workspace')
@endsection
