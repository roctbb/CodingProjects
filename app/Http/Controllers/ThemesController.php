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
        // Check if user is banned from creating themes
        if (!\Auth::user()->canCreateThemes()) {
            return redirect('/insider/themes')->with('error', 'Вам запрещено создавать темы');
        }

        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',

        ]);

        $theme = new \App\Theme();
        $theme->user_id = \Auth::id();
        $theme->name = $request->name;
        $theme->description = $request->description;
        $theme->price = $request->price;
        $theme->image = $request->image;
        $theme->moderation_status = 'pending';
        $theme->save();

        // Create the theme files in storage
        \Storage::disk('local')->put('themes/'.$theme->id.'/script.js', $request->js);
        \Storage::disk('local')->put('themes/'.$theme->id.'/style.css', $request->css);

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
        if (!($user->role === 'admin' || $user->isThemeModerator() || $user->role === 'teacher' || (int)$theme->user_id === (int)$user->id)) {
            abort(403, 'Нет доступа к редактированию этой темы');
        }
        return view('themes.edit', compact('theme'));
    }

    function edit($id, Request $request)
    {
        $theme = \App\Theme::find($id);
        $user = \Auth::user();
        if (!($user->role === 'admin' || $user->isThemeModerator() || $user->role === 'teacher' || (int)$theme->user_id === (int)$user->id)) {
            abort(403, 'Нет доступа к редактированию этой темы');
        }

        // Check if user is banned from creating themes
        if (!$user->canCreateThemes()) {
            return redirect('/insider/themes')->with('error', 'Вам запрещено редактировать темы');
        }

        $this->validate($request, [
            'name' => 'required|string',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',

        ]);
        \App\Theme::modify($id, $request->name, $request->description, $request->image, $request->price, $request->css, $request->js);

        // Set theme back to pending status after modification
        $theme->moderation_status = 'pending';
        $theme->moderated_at = null;
        $theme->moderated_by = null;
        $theme->save();

        return redirect('/insider/themes/' . $id);
    }

    function delete($id)
    {
        $theme = \App\Theme::find($id);
        $user = \Auth::user();
        if (!($user->role === 'admin' || $user->isThemeModerator() || $user->role === 'teacher' || (int)$theme->user_id === (int)$user->id)) {
            abort(403, 'Нет доступа к удалению этой темы');
        }
        $theme->delete();
        return redirect('/insider/themes');
    }

    // Theme Moderation Methods
    function moderationIndex()
    {
        $pendingThemes = \App\Theme::where('moderation_status', 'pending')->with('user')->get();
        $approvedThemes = \App\Theme::where('moderation_status', 'approved')->with('user', 'moderator')->get();
        $bannedThemes = \App\Theme::where('moderation_status', 'banned')->with('user', 'moderator')->get();
        $bannedUsers = \App\User::where('theme_banned', true)->get();

        return view('themes.moderation', compact('pendingThemes', 'approvedThemes', 'bannedThemes', 'bannedUsers'));
    }

    function moderateView($id)
    {
        $theme = \App\Theme::with('user', 'moderator')->findOrFail($id);
        return view('themes.moderate', compact('theme'));
    }

    function approve(Request $request, $id)
    {
        $theme = \App\Theme::findOrFail($id);
        $theme->approve(\Auth::id());
        return redirect('/insider/themes/moderation')->with('success', 'Тема одобрена');
    }

    function banTheme(Request $request, $id)
    {
        $theme = \App\Theme::findOrFail($id);
        $theme->ban(\Auth::id());
        return redirect('/insider/themes/moderation')->with('success', 'Тема забанена');
    }

    function unbanTheme($id)
    {
        $theme = \App\Theme::findOrFail($id);
        $theme->moderation_status = 'approved';
        $theme->moderated_at = now();
        $theme->moderated_by = \Auth::id();
        $theme->save();
        return redirect('/insider/themes/moderation')->with('success', 'Тема разбанена');
    }

    function banUser($id)
    {
        $user = \App\User::findOrFail($id);
        $user->banFromThemes();
        return redirect('/insider/themes/moderation')->with('success', 'Пользователь забанен');
    }

    function unbanUser($id)
    {
        $user = \App\User::findOrFail($id);
        $user->unbanFromThemes();
        return redirect('/insider/themes/moderation')->with('success', 'Пользователь разбанен');
    }
}
