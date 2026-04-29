<script type="text/javascript" src="{{ url('assets/js/jquery.min.js') }}"></script>
<script type="text/javascript" src="{{ url('assets/js/popper.min.js') }}"></script>
<script type="text/javascript" src="{{ url('assets/js/bootstrap.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/autosize.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/dropzone.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/draggable.umd.min.js') }}"></script>
<script type="text/javascript" src="{{ url('assets/js/swap-animation.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/list.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/flatpickr.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/easymde.min.js') }}"></script>
<script type="text/javascript" src="{{ asset('build/js/vendor/prism.js') }}"></script>
<script type="text/javascript">
    (function () {
        if (window.EasyMDE) {
            return;
        }
        if (window.module && window.module.exports) {
            window.EasyMDE = window.module.exports.default || window.module.exports;
            return;
        }
        if (window.exports && (window.exports.default || window.exports.EasyMDE)) {
            window.EasyMDE = window.exports.default || window.exports.EasyMDE;
        }
    })();
</script>
<script type="text/javascript" src="{{ url('assets/js/theme.js') }}"></script>
