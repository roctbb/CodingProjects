<?php

namespace App\Services;

class EmailVerify
{
    private $isEnabled;
    public function __construct()
    {
        $this->isEnabled = config('app.enable_email_verification');
    }

    public function getDate()
    {
        if (!$this->isEnabled) return \Carbon\Carbon::now();
        return null;
    }
}

?>
