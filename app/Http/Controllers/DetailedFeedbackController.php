<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\DetailedFeedback;
use App\Notifications\NewExtremeFeedback;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DetailedFeedbackController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function feedback_view($key, Request $request)
    {
        $user = \Auth::user();
        $queries = DetailedFeedback::getForms($user, $key);

        return view('/feedback/form', compact('queries'));
    }

    public function feedback($key, Request $request)
    {
        $user = \Auth::user();
        $queries = DetailedFeedback::getForms($user, $key);

        foreach ($queries as $query) {
            if ($request->has($query->id . '_missed')) {
                $query->is_missed = true;
            } else {
                $this->validate($request, [$query->id . '_mark' => 'required|integer|min:1|max:5']);
                $query->mark = (int)$request->get($query->id . '_mark');
                if ($query->mark < 4) {
                    if ($query->mark < 3) {
                        $admin = User::findOrFail(1);
                        $when = Carbon::now()->addSeconds(1);
                        $admin->notify((new NewExtremeFeedback($query))->delay($when));

                    }

                    if ($request->has($query->id . '_late')) $query->is_late = true;
                    if ($request->has($query->id . '_unclear')) $query->is_unclear = true;
                    if ($request->has($query->id . '_unprepaired')) $query->is_unprepaired = true;
                    if ($request->has($query->id . '_notanswering')) $query->is_conflict = true;
                    if ($request->has($query->id . '_need_contact')) $query->need_communication = true;
                    if ($request->has($query->id . '_comment') and $request->get($query->id . '_comment') != '')
                        $query->comment = $request->get($query->id . '_comment');
                }

            }

            $query->is_filled = true;
            $query->save();
        }

        if (count($queries) != 0) {
            CoinTransaction::register($user->id, 1, 'Обратная связь после занятий');
        }


        return redirect('/');
    }
}
