@if($errors->any())
<section class="gc-form-errors" role="alert" tabindex="-1" data-form-errors>
    <h2>Проверьте поля формы</h2>
    <ul class="mb-0">@foreach($errors->getMessages() as $field => $messages)<li><a href="#{{ explode('.', $field)[0] }}" data-field-error-link>{{ $messages[0] }}</a></li>@endforeach</ul>
</section>
@endif
