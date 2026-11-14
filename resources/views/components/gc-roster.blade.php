@props(['id', 'labelledby'])
<div class="gc-master-detail__list">
    <aside {{ $attributes->class(['offcanvas-md offcanvas-start gc-responsive-roster']) }} id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $labelledby }}">
        {{ $slot }}
    </aside>
</div>
