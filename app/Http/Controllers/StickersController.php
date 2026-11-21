<?php

namespace App\Http\Controllers;

use App\Sticker;
use Illuminate\Http\Request;

class StickersController extends Controller
{
    public function index(Request $request)
    {
        $editing = $request->filled('edit') ? Sticker::findOrFail($request->integer('edit')) : null;
        return view('settings.stickers', ['groups' => Sticker::catalog()->groupBy('collection'), 'editing' => $editing]);
    }

    public function store(Request $request)
    {
        $values = $request->validate($this->labels() + ['image' => Sticker::imageRules()]);
        $sticker = Sticker::create($values);
        return to_route('settings.stickers.index', ['edit' => $sticker->id])->with('status', 'Наклейка добавлена.');
    }

    public function update(Request $request, Sticker $sticker)
    {
        $sticker->update($request->validate($this->labels()));
        return to_route('settings.stickers.index', ['edit' => $sticker->id])->with('status', 'Наклейка сохранена.');
    }

    private function labels(): array
    {
        return ['name' => 'required|string|max:160', 'collection' => 'required|string|max:100'];
    }
}
