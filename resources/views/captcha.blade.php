@if (config('app.enable_recaptcha'))
    @pushOnce('captcha')
        {!! \NoCaptcha::renderJs() !!}
    @endPushOnce
    <div id="captcha-field" class="gc-captcha mt-4" tabindex="-1">
        {!! \NoCaptcha::display(['data-size' => 'compact']) !!}
    </div>
    @if ($errors->has('g-recaptcha-response'))
        <span class="text-danger small d-block mt-1">
            <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
        </span>
    @endif
@endif
