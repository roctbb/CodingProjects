@if (config('app.enable_recaptcha'))
    <div class="gc-captcha mb-3">
        {!! \NoCaptcha::display() !!}
    </div>
    @if ($errors->has('g-recaptcha-response'))
        <span class="text-danger small d-block mt-1">
            <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
        </span>
    @endif
@endif
