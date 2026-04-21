@if (config('app.enable_recaptcha'))
    {!! \NoCaptcha::display() !!}
    @if ($errors->has('g-recaptcha-response'))
        <span class="invalid-feedback d-block">
                          <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                      </span>
    @endif
@endif
