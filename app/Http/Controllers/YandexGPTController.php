<?php

namespace App\Http\Controllers;

use App\Services\ChatGptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YandexGPTController extends Controller
{
    private $chatGpt;

    public function __construct(?ChatGptService $chatGpt = null)
    {
        $this->chatGpt = $chatGpt ?: app(ChatGptService::class);
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
            $improvedText = $this->callChatGpt($text, $action);

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

    private function callChatGpt($text, $action)
    {
        $prompt = $this->getPromptForAction($action);

        return $this->chatGpt->generate([
            ['role' => 'system', 'content' => $prompt],
            ['role' => 'user', 'content' => $text],
        ], ['timeout' => 60]);
    }

    private function callYandexGPT($text, $action)
    {
        return $this->callChatGpt($text, $action);
    }

    private function getPromptForAction($action)
    {
        switch ($action) {
            case 'fix_typos':
                return 'Исправь орфографические и пунктуационные ошибки в тексте. Сохрани оригинальный стиль и структуру. ОБЯЗАТЕЛЬНО сохрани все элементы markdown-разметки: изображения ![alt](url), выделения (**, __, _), ссылки [text](url), код `code`, блоки кода ```code```, заголовки #, списки, таблицы и другие элементы разметки. Верни только исправленный текст без дополнительных комментариев.';

            case 'improve_style':
                return 'Улучши стиль и читаемость текста, сделай его более ясным и понятным. Сохрани основной смысл и структуру. ОБЯЗАТЕЛЬНО сохрани все элементы markdown-разметки: изображения ![alt](url), выделения (**, __, _), ссылки [text](url), код `code`, блоки кода ```code```, заголовки #, списки, таблицы и другие элементы разметки. Верни только улучшенный текст без дополнительных комментариев.';

            case 'both':
                return 'Исправь орфографические и пунктуационные ошибки, а также улучши стиль и читаемость текста. Сделай его более ясным и понятным, сохранив основной смысл. ОБЯЗАТЕЛЬНО сохрани все элементы markdown-разметки: изображения ![alt](url), ссылки [text](url), код `code`, блоки кода ```code```, заголовки #, списки, таблицы и другие элементы разметки. Верни только исправленный и улучшенный текст без дополнительных комментариев.';

            default:
                return 'Исправь ошибки и улучши текст. ОБЯЗАТЕЛЬНО сохрани все элементы markdown-разметки.';
        }
    }
}
