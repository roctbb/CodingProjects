@php
    $vendorScriptPath = public_path('build/js/vendor.js');
    $vendorScriptVersion = file_exists($vendorScriptPath) ? filemtime($vendorScriptPath) : null;
@endphp
<script type="module" src="{{ asset('build/js/vendor.js') }}@if($vendorScriptVersion)?v={{ $vendorScriptVersion }}@endif"></script>
