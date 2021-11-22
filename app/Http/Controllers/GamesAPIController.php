<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GamesAPIController extends Controller
{
    //

    public function info($id)
    {
        $game = \App\Game::findOrFail($id);
        return [
            'id' => $game->id,
            'title' => $game->title,
            'desc' => $game->description,

            'code' => $game->code()
        ];
    }

    public function update($id, Request $request)
    {
        $game = \App\Game::findOrFail($id);
        
        \App\Game::modify(
            $id, 
            $game->title, 
            $game->description, 
            $request->code
        );

    }
}
