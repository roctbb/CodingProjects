<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthoringPreviewController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'teacher']);
    }

    public function __invoke(Request $request)
    {
        $data = $request->validate(['text' => 'nullable|string']);
        return response()->json(['html' => parsedown_math($data['text'] ?? '')]);
    }
}
