<?php

namespace App\Http\Controllers;

use App\Services\RewardNotices;
use Illuminate\Http\Request;

final class RewardNoticesController extends Controller
{
    public function claim(Request $request, RewardNotices $notices)
    {
        return response()->json(['notice' => $notices->claim($request->user())])->header('Cache-Control', 'no-store, private');
    }
}
