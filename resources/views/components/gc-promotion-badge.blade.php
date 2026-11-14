@props(['percent' => 0, 'name' => null])
@if($percent > 0)<span class="gc-promotion-badge" @if($name) title="{{ $name }}" @endif><i class="fas fa-tag" aria-hidden="true"></i><span class="visually-hidden">Скидка </span>−{{ $percent }}%</span>@endif
