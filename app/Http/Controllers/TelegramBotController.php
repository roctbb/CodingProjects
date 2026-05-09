<?php

namespace App\Http\Controllers;

use App\Services\TelegramBindService;
use Illuminate\Http\Request;

class TelegramBotController extends Controller
{
    public function webhook(Request $request, TelegramBindService $bindService)
    {
        if (!$this->isAllowed($request)) {
            return response()->json(['ok' => false], 403);
        }

        $bindService->handleUpdate($request->all());

        return response()->json(['ok' => true]);
    }

    private function isAllowed(Request $request)
    {
        $secret = config('services.telegram.webhook_secret');

        if (!$secret) {
            return true;
        }

        return hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token'))
            || hash_equals($secret, (string) $request->route('secret'));
    }
}
