@props(['title', 'description' => null])
<section {{ $attributes->class(['gc-form-section']) }}>
    <header class="gc-form-section__heading"><h2>{{ $title }}</h2>@if($description)<p class="gc-help-text mb-0">{{ $description }}</p>@endif</header>
    <div class="gc-form-section__fields">{{ $slot }}</div>
</section>
