<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ThemesController extends Controller
{
    //
    function getCSS($id)
    {
        $theme = \App\Theme::find($id);
        return response($theme->css())
            ->header('Content-Type', 'text/css');
    }
    
    function getJS($id)
    {
        $theme = \App\Theme::find($id);
        return response($theme->js())
            ->header('Content-Type', 'application/javascript');
    }
    function buy($id)
    {
        $theme = \App\Theme::find($id);
        if (\Auth::user()->balance() < $theme->price || \Auth::user()->hasTheme($id)) abort(403);
        $themeBought = \App\ThemeBought::create([
            "user_id" => \Auth::id(),
            "theme_id" => $id    
        ]);

        if ($theme->price != 0)
        {
            // Списываем деньги с покупателя
            \App\CoinTransaction::register(\Auth::id(), -$theme->price, "Купил тему ".$theme->name);
            // Начисляем деньги продавцу
            if ($theme->user_id && $theme->user_id != \Auth::id()) {
                \App\CoinTransaction::register($theme->user_id, $theme->price, "Продал тему ".$theme->name);
            }
        }
        return redirect("/insider/themes");
    }
    function index(Request $request)
    {
        $themes = \App\Theme::all();
        $is_try = $request->try != null;
        $try = null;
        if ($is_try)
        {
            $try = \App\Theme::find($request->try);
        }
        return view('themes.index', compact('themes', 'try', 'is_try'));
    }

    function details(Request $request, $id)
    {
        $theme = \App\Theme::find($id);
        
        $is_try = $request->try != null;
        return view('themes.details', compact('theme', 'is_try'));
    
    }

    function createView()
    {
        return view('themes.create');
    } 

    function wear($id)
    {
        if (!\Auth::user()->hasTheme($id)) abort(403);
        \Auth::user()->wearTheme($id);
        return back();
    }

    function takeOff($id)
    {
        \Auth::user()->takeOffTheme($id);
        return back();

    }
    function create(Request $request)
    {        
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',

        ]);

        $theme = \App\Theme::make(\Auth::id(), $request->name, $request->description, $request->css, $request->js, $request->price, $request->image);
        
        // Автоматически добавить тему в купленные
        \App\ThemeBought::create([
            'user_id' => \Auth::id(),
            'theme_id' => $theme->id
        ]);

        return redirect('/insider/themes/' . $theme->id);
        
    }
    
    function editView($id)
    {
        $theme = \App\Theme::find($id);
        $user = \Auth::user();
        if (!($user->role === 'teacher' || $theme->user_id === $user->id)) {
            abort(403, 'Нет доступа к редактированию этой темы');
        }
        return view('themes.edit', compact('theme'));
    }

    function edit($id, Request $request)
    {
        $theme = \App\Theme::find($id);
        $user = \Auth::user();
        if (!($user->role === 'teacher' || $theme->user_id === $user->id)) {
            abort(403, 'Нет доступа к редактированию этой темы');
        }
        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',

        ]);
        \App\Theme::modify($id, $request->name, $request->description, $request->image, $request->price, $request->css, $request->js);
        return redirect('/insider/themes/' . $id);
    }

    function delete($id)
    {
        $theme = \App\Theme::find($id);
        $user = \Auth::user();
        if (!($user->role === 'teacher' || $user->role === 'admin' || $theme->user_id === $user->id)) {
            abort(403, 'Нет доступа к удалению этой темы');
        }
        $theme->delete();
        return redirect('/insider/themes');
    }
}
