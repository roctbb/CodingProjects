<?php
namespace App\MaterialReview;

class Problem extends \RuntimeException
{
    public function __construct(public string $error, string $message, public array $details = [], public int $status = 422)
    { parent::__construct($message); }

    public function json(): array { return ['error'=>$this->error,'message'=>$this->getMessage(),'details'=>$this->details]; }
    public function render($request) { return response()->json($this->json(),$this->status); }
}
