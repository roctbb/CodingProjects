<?php

return [
    'url' => rtrim(env('GUARD_URL', ''), '/'),
    'secret' => env('GUARD_IDENTITY_SECRET', ''),
];
