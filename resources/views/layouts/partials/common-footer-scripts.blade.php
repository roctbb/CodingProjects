@php
    $cpuiDatepickers = $cpuiDatepickers ?? false;
    $cpuiApplyLinkify = $cpuiApplyLinkify ?? true;
    $cpuiLinkifySelector = $cpuiLinkifySelector ?? 'div';
    $cpuiInitPopovers = $cpuiInitPopovers ?? false;
    $cpuiPopoverSelector = $cpuiPopoverSelector ?? '[data-bs-toggle="popover"]';
    $cpuiPopoverOptions = $cpuiPopoverOptions ?? ['trigger' => 'hover'];
    $enableBlankTargetLinks = $enableBlankTargetLinks ?? true;
    $blankTargetSelector = $blankTargetSelector ?? 'div.markdown a';
    $enableMathJaxTypeset = $enableMathJaxTypeset ?? false;
    $includeActionFormScript = $includeActionFormScript ?? false;
    $includeYandexMetrika = $includeYandexMetrika ?? true;
@endphp

<form class="cp-d-none" id="logout-form" method="POST" action="{{ route('logout') }}">{{ csrf_field() }}</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.CPUI) {
            window.CPUI.migrateLegacyBootstrapDataApi();
            @if($cpuiDatepickers)
                window.CPUI.initDatepickers('.date');
            @endif
            @if($cpuiApplyLinkify)
                window.CPUI.applyLinkify(@json($cpuiLinkifySelector));
            @endif
            @if($cpuiInitPopovers)
                window.CPUI.initPopovers(@json($cpuiPopoverSelector), @json($cpuiPopoverOptions));
            @endif
        }

        @if($enableBlankTargetLinks)
            document.querySelectorAll(@json($blankTargetSelector)).forEach(function (link) {
                link.setAttribute('target', 'blank');
            });
        @endif

        @if($enableMathJaxTypeset)
            if (window.MathJax && typeof window.MathJax.typesetPromise === 'function') {
                window.MathJax.typesetPromise();
            }
        @endif
    });

</script>

@if($includeActionFormScript)
    @include('layouts.partials.action-form-script')
@endif

@if($includeYandexMetrika)
    <script type="text/javascript">
        (function (m, e, t, r, i, k, a) {
            m[i] = m[i] || function () {
                (m[i].a = m[i].a || []).push(arguments)
            };
            m[i].l = 1 * new Date();
            k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a)
        })
        (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

        ym(55625236, "init", {
            clickmap: true,
            trackLinks: true,
            accurateTrackBounce: true,
            webvisor: true
        });
    </script>
    <noscript>
        <div><img src="https://mc.yandex.ru/watch/55625236" class="cp-abs-offscreen" alt=""/></div>
    </noscript>
@endif
