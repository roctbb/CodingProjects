<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GamesController extends Controller
{
    //
    public function frame($id)
    {
        $game = \App\Game::findOrFail($id);
        return view('games/frame', compact('game'));
    }

    public function create()
    {
        return view('games/create');
    }

    public function ide($id)
    {
        $game = \App\Game::findOrFail($id);
        return view('games/ide', compact('game'));

    }

    public function comment($id, Request $request)
    {
        $messages = [
            'comment.required' => 'Это поле обязательно',
            'comment.min' => 'Это поле должно содержать как минимум 5 символов'
        ];
        $validator = \Validator::make($request->all(), [
            'comment' => 'required|min:5'
        ], $messages);

        if ($validator->fails()) {
            return redirect('insider/games/' . $id . '/#comment')->withErrors($validator->errors());
        }

        \App\GameComments::create([
            'user_id' => \Auth::id(),
            'game_id' => $id,
            'comment' => $request->comment
        ]);

        return redirect('insider/games/' . $id . '/#comment');
    }

    public function reward($id)
    {
        $game = \App\Game::findOrFail($id);

        return view('games/reward', compact('game'));
    }

    public function sendReward($id, Request $request)
    {
        $game = \App\Game::findOrFail($id);
        $messages = [
            'reward.min' => 'Минимальная сумма награды - 1 GeekCoin',
            'reward.max' => 'На вашем балансе не хватает GeekCoin',
            'comment.min' => 'Комментарий обязятелен'
        ];
        $request->validate([
            'reward' => 'numeric|min:1' . (\Auth::user()->is_teacher ? '' : '|max:' . \Auth::user()->balance()),
            'comment' => 'min:1'
        ], $messages);
        \App\GameReward::register($game->user->id, $id, $request->reward, $request->comment);

        return redirect('/insider/games/' . $game->id);
    }

    public function upvote($id)
    {
        $game = \App\Game::findOrFail($id);

        $game->vote(1);
        return back();
    }

    public function downvote($id)
    {
        $game = \App\Game::findOrFail($id);

        $game->vote(-1);
        return back();
    }

    public function commentDelete($cid)
    {
        \App\GameComments::findOrFail($cid)->delete();
        return back();
    }

    public function delete($id)
    {

        \App\Game::findOrFail($id)->delete();

        return redirect('/insider/games/');
    }

    public function edit($id)
    {
        $game = \App\Game::findOrFail($id);

        return view('games/edit', compact('game'));
    }

    public function store(Request $request)
    {
        $messages = [
            'title.required' => 'Название не может быть пустым',
            'description.required' => 'Описание не может быть пустым',

            'title.min' => 'Заголовок должен содержать хотя-бы 3 символа',
            'description.min' => 'Описание должно содержать хотя-бы 5 символов',

            'title.max' => 'Слишком длинное название, его длина не должна превышать 255 символов'
        ];
        $request->validate([
            'type' => 'required',
            'title' => 'required|min:3|max:255',
            'description' => 'required|min:5'
        ], $messages);

        $template = '';

        if ($request->type == 'webgl') {
            $template = \App\Game::webglTemplate();
        } else if ($request->type == 'canvas') {
            $template = \App\Game::template();

        }
        $id = \App\Game::make(
            \Auth::id(),
            $request->title,
            $request->description,
            $template,
            $request->type
        );


        return redirect('insider/games/' . $id . '/ide');
    }


    public function update($gameId, Request $request)
    {
        $messages = [
            'title.required' => 'Название не может быть пустым',
            'description.required' => 'Описание не может быть пустым',

            'title.min' => 'Заголовок должен содержать хотя-бы 3 символа',
            'description.min' => 'Описание должно содержать хотя-бы 5 символов',

            'title.max' => 'Слишком длинное название, его длина не должна превышать 255 символов'
        ];
        $request->validate([
            'title' => 'required|min:3|max:255',
            'description' => 'required|min:5'
        ], $messages);


        \App\Game::modify(
            $gameId,
            $request->title,
            $request->description,
            \App\Game::findOrFail($gameId)->code()
        );


        return redirect('insider/games/' . $gameId . '/ide');
    }

    public function viewsource($id)
    {
        $game = \App\Game::findOrFail($id);
        return response($game->file(), 200)->header('Content-Type', 'text/javascript');
    }

    public function play($id)
    {
        $game = \App\Game::findOrFail($id);
        return view('games/play', compact('game'));
    }

    public function index()
    {
        $games = \App\Game::all()->sortByDesc(function ($item) {
            $ratio = $item->upvotes() / (Carbon::now()->timestamp - $item->created_at->timestamp);
            return $ratio;
        })->values();

        return view('games/index', compact('games'));
    }
}
