<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class YandexGPTController extends Controller
{
    public function improveText(Request $request)
    {
        $request->validate([
            'text' => 'required|string|max:10000',
            'action' => 'required|string|in:fix_typos,improve_style,both'
        ]);

        $text = $request->input('text');
        $action = $request->input('action');

        try {
            $improvedText = $this->callYandexGPT($text, $action);

            return response()->json([
                'success' => true,
                'original_text' => $text,
                'improved_text' => $improvedText
            ]);
        } catch (\Exception $e) {
            Log::error('YandexGPT API Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'error' => 'Произошла ошибка при обработке текста. Попробуйте позже.'
            ], 500);
        }
    }

    private function callYandexGPT($text, $action)
    {
        $apiKey = config('services.yandexgpt.api_key');
        $folderId = config('services.yandexgpt.folder_id');
        $model = config('services.yandexgpt.model');
        $url = config('services.yandexgpt.url');

        if (!$apiKey || !$folderId) {
            throw new \Exception('YandexGPT API credentials not configured');
        }

        $prompt = $this->getPromptForAction($action);

        $client = new Client();

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Api-Key ' . $apiKey,
                    'Content-Type' => 'application/json'
                ],
                'json' => [
                    'modelUri' => "gpt://{$folderId}/{$model}",
                    'completionOptions' => [
                        'stream' => false,
                        'temperature' => 0.3,
                        'maxTokens' => 2000
                    ],
                    'messages' => [
                        [
                            'role' => 'system',
                            'text' => $prompt
                        ],
                        [
                            'role' => 'user',
                            'text' => $text
                        ]
                    ]
                ]
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            throw new \Exception('YandexGPT API request failed: ' . $e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            throw new \Exception('YandexGPT API request failed: ' . $response->getBody()->getContents());
        }

        $data = json_decode($response->getBody()->getContents(), true);

        if (!isset($data['result']['alternatives'][0]['message']['text'])) {
            throw new \Exception('Invalid response format from YandexGPT API');
        }

        \Log::info($data);

        return $data['result']['alternatives'][0]['message']['text'];
    }

    private function getPromptForAction($action)
    {
        switch ($action) {
            case 'fix_typos':
                return 'Исправь орфографические и пунктуационные ошибки в тексте. Сохрани оригинальный стиль и структуру. Верни только исправленный текст без дополнительных комментариев.';

            case 'improve_style':
                return 'Улучши стиль и читаемость текста, сделай его более ясным и понятным. Сохрани основной смысл и структуру. Верни только улучшенный текст без дополнительных комментариев.';

            case 'both':
                return 'Исправь орфографические и пунктуационные ошибки, а также улучши стиль и читаемость текста. Сделай его более ясным и понятным, сохранив основной смысл. Верни только исправленный и улучшенный текст без дополнительных комментариев.';

            default:
                return 'Исправь ошибки и улучши текст.';
        }
    }
}
