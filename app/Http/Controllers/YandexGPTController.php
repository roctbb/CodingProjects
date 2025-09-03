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
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : '';

            Log::error('YandexGPT API Request Exception', [
                'status_code' => $statusCode,
                'response_body' => $responseBody,
                'message' => $e->getMessage()
            ]);

            if ($statusCode === 401) {
                throw new \Exception('YandexGPT API authentication failed. Please check your API key and folder ID.');
            } elseif ($statusCode === 404) {
                throw new \Exception('YandexGPT API endpoint not found. Please check the API URL and model configuration.');
            } elseif ($statusCode === 429) {
                throw new \Exception('YandexGPT API rate limit exceeded. Please try again later.');
            } else {
                throw new \Exception('YandexGPT API request failed: ' . $e->getMessage());
            }
        }

        if ($response->getStatusCode() !== 200) {
            $responseBody = $response->getBody()->getContents();
            Log::error('YandexGPT API non-200 response', [
                'status_code' => $response->getStatusCode(),
                'response_body' => $responseBody
            ]);
            throw new \Exception('YandexGPT API request failed with status ' . $response->getStatusCode() . ': ' . $responseBody);
        }

        $responseBody = $response->getBody()->getContents();
        $data = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('YandexGPT API JSON decode error', [
                'json_error' => json_last_error_msg(),
                'response_body' => $responseBody
            ]);
            throw new \Exception('Invalid JSON response from YandexGPT API: ' . json_last_error_msg());
        }

        if (!isset($data['result']['alternatives'][0]['message']['text'])) {
            Log::error('YandexGPT API invalid response format', [
                'response_data' => $data
            ]);
            throw new \Exception('Invalid response format from YandexGPT API. Expected text not found in response.');
        }

        $improvedText = $data['result']['alternatives'][0]['message']['text'];

        if (empty(trim($improvedText))) {
            Log::warning('YandexGPT API returned empty text', [
                'original_text' => $text,
                'response_data' => $data
            ]);
            throw new \Exception('YandexGPT API returned empty text. Please try again.');
        }

        return $improvedText;
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
