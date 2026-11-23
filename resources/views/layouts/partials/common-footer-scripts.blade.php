@php
    $assetManifestPath = public_path('build/manifest.json');
    $assetManifest = is_file($assetManifestPath) ? json_decode(file_get_contents($assetManifestPath), true) : [];
    $vendorScript = $assetManifest['resources/assets/js/vendor.js']['file'] ?? 'js/vendor.js';
@endphp
<script type="module" src="{{ asset('build/'.$vendorScript) }}"></script>
<x-gc-reward-notices />
<x-gc-screen-pet />
@include('layouts.partials.course-material-access')
