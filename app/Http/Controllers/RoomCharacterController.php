<?php

namespace App\Http\Controllers;

use App\RoomCharacterGeneration;
use App\Services\RoomCharacterGenerator;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RoomCharacterController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function store(Request $request, int $user_id, RoomCharacterGenerator $generator)
    {
        abort_unless((int) $request->user()->id === $user_id, 403);
        abort_unless(Schema::hasTable('room_character_generations'), 503, 'Генерация пока недоступна.');
        $data = $request->validate(['request_id' => ['required', 'uuid']]);
        $generation = $generator->purchase($user_id, $data['request_id']);
        if (!$request->expectsJson()) {
            return redirect('/insider/profile/' . $user_id . '#learning-avatar');
        }
        return response()->json($this->payload($generation), $generation->isActive() ? 202 : 200)
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request, int $user_id, int $generation_id)
    {
        abort_unless((int) $request->user()->id === $user_id, 403);
        $generation = RoomCharacterGeneration::where('user_id', $user_id)->findOrFail($generation_id);
        return response()->json($this->payload($generation))->header('Cache-Control', 'no-store');
    }

    private function payload(RoomCharacterGeneration $generation): array
    {
        $user = User::findOrFail($generation->user_id);
        return [
            'id' => $generation->id, 'status' => $generation->status, 'message' => $generation->statusMessage(),
            'balance' => (int) $user->balance(), 'cost' => $generation->cost,
            'image_url' => $user->roomCharacterImageUrl(),
            'status_url' => route('room-character.show', [$user->id, $generation->id]),
            'next_request_id' => $generation->isActive() ? null : (string) Str::uuid(),
        ];
    }
}
