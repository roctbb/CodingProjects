<?php

namespace App\Http\Controllers;

use App\Services\TextImprover;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TextImprovementController extends Controller
{
    public function __construct(private TextImprover $textImprover)
    {
    }

    public function improveText(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:10000',
            'action' => 'required|string|in:fix_typos,improve_style,both'
        ]);

        $text = $request->input('text');
        $action = $request->input('action');

        try {
            $improvedText = $this->textImprover->improve($text, $action);

            return response()->json([
                'success' => true,
                'original_text' => $text,
                'improved_text' => $improvedText
            ]);
        } catch (\Exception $e) {
            Log::error('ChatGPT text improvement error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Произошла ошибка при обработке текста. Попробуйте позже.'
            ], 500);
        }
    }
}
