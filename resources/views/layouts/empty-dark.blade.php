<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{{ config('app.name', 'Laravel') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title')
        - {{ config('app.name', 'Laravel') }}
    </title>

    @include('layouts.partials.npm-vendor-assets')

    <script>hljs.initHighlightingOnLoad();</script>
    @yield('head')
</head>
<body class="ge-empty-dark geek-auth-body">
<div class="container ge-empty-dark__container">
    <div class="row justify-content-center">
        <div class="col-11">
            <div class="align-items-center justify-content-center auth-shell">
                @include('layouts.partials.flash-alert')

                <div class="auth-shell-card">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</div>

{!! \NoCaptcha::renderJs() !!}

@php
    $cpuiDatepickers = true;
    $cpuiTabsSelector = '.nav-tabs a, .nav-pills a';
    $cpuiInitPopovers = true;
    $includeActionFormScript = false;
@endphp
@include('layouts.partials.common-footer-scripts')
<script>
    (function () {
        function evaluatePasswordStrength(value) {
            var score = 0;
            if (value.length >= 8) score += 1;
            if (/[A-ZА-Я]/.test(value) && /[a-zа-я]/.test(value)) score += 1;
            if (/\d/.test(value)) score += 1;
            if (/[^A-Za-zА-Яа-я0-9]/.test(value)) score += 1;

            if (!value.length) return { level: 'empty', text: 'Надежность пароля: не указано', percent: 0 };
            if (score <= 1) return { level: 'low', text: 'Надежность пароля: низкая', percent: 34 };
            if (score <= 3) return { level: 'medium', text: 'Надежность пароля: средняя', percent: 68 };
            return { level: 'high', text: 'Надежность пароля: высокая', percent: 100 };
        }

        function bindPasswordStrength() {
            var blocks = document.querySelectorAll('[data-password-strength-target]');
            blocks.forEach(function (block) {
                var inputId = block.getAttribute('data-password-strength-target');
                var input = inputId ? document.getElementById(inputId) : null;
                var label = block.querySelector('.auth-password-strength__label');
                var bar = block.querySelector('.auth-password-strength__bar');
                if (!input || !label || !bar) return;

                var update = function () {
                    var state = evaluatePasswordStrength(input.value || '');
                    label.textContent = state.text;
                    bar.style.width = state.percent + '%';
                    block.classList.remove('is-low', 'is-medium', 'is-high', 'is-empty');
                    block.classList.add('is-' + state.level);
                };

                input.addEventListener('input', update);
                update();
            });
        }

        function bindResendCountdown() {
            var button = document.getElementById('passwordEmailSubmit');
            if (!button) return;
            var note = document.getElementById('passwordEmailResendNote');
            var seconds = parseInt(button.getAttribute('data-resend-seconds') || '0', 10);
            if (!seconds || isNaN(seconds)) return;

            button.disabled = true;
            var timer = setInterval(function () {
                seconds -= 1;
                if (seconds <= 0) {
                    clearInterval(timer);
                    button.disabled = false;
                    button.textContent = 'Отправить письмо повторно';
                    button.removeAttribute('data-resend-seconds');
                    if (note) {
                        note.textContent = 'Теперь можно отправить письмо повторно.';
                    }
                    return;
                }

                button.textContent = 'Повторная отправка через ' + seconds + ' сек';
                if (note) {
                    note.textContent = 'Пожалуйста, подождите перед повторной отправкой.';
                }
            }, 1000);
        }

        function togglePassword(button) {
            var targetId = button.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;
            if (!input) {
                return;
            }

            var isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-label', isPassword ? 'Скрыть пароль' : 'Показать пароль');
            button.classList.toggle('is-active', isPassword);
        }

        document.addEventListener('click', function (event) {
            var button = event.target.closest('.auth-password-toggle');
            if (!button) {
                return;
            }
            event.preventDefault();
            togglePassword(button);
        });

        bindPasswordStrength();
        bindResendCountdown();
    })();
</script>
</body>
</html>
