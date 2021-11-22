<?php

namespace App\Services;

class Recaptcha
{
  private $isEnabled;
  public function __construct()
  {
    $this->isEnabled = config('app.enable_recaptcha');
  }

  public function getValidationString()
  {
    if ($this->isEnabled) return 'required|captcha';
    return '';
  }
}

?>
