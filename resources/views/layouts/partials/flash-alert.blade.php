@if(Session::has('alert-class') && Session::get('alert-destination') == 'head')
    <div class="alert {{ Session::get('alert-class') }} alert-dismissible" role="alert">
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <strong>{{ Session::get('alert-title') }}</strong> {{ Session::get('alert-text') }}
    </div>
@endif
