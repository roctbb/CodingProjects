<?php

namespace App\Http\Controllers;

use App\CoinTransaction;
use App\CompletedCourse;
use App\Course;
use App\EducationalResult;
use App\FeedbackRecord;
use App\MarketDeal;
use App\MarketGood;
use App\Notifications\NewExtremeFeedback;
use App\Notifications\NewOrder;
use App\Program;
use App\ProgramStep;
use App\Http\Controllers\Controller;
use App\Provider;
use App\ResultScale;
use App\Task;
use App\User;
use App\Lesson;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Auth;
use Log;

class BotController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth')->except('saveFeedback');
        $this->middleware('verified')->except('saveFeedback');
        $this->middleware('admin')->only(['sendView', 'send']);
    }

    public function sendView()
    {
        return view('bot.send');
    }

    public function send(Request $request)
    {
        try {
            $message = $request->message;

            $client = new \GuzzleHttp\Client();
            $res = $client->post(config('bot.vk_bot').'/message', [
                'form_params' => [
                    'message' => $message,
                    'key' => config('bot.vk_bot_key')
                ]
            ]);
            $result = json_decode($res->getBody()->getContents());
            if ($result->state == "ok")
            {
                return redirect()->back()->withErrors(array('message' => 'Сообщение отправлено'));
            }
            else {
                return redirect()->back()->withErrors(array('message' => 'Проверьте правильность кода...'));
            }
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors(array('message' => 'Ошибка связи с сервисом...'));
        }
    }

    public function activateView()
    {
        return view('bot.activate');
    }

    public function activate(Request $request)
    {
        try {
            $code = $request->code;

            $client = new \GuzzleHttp\Client();
            $res = $client->post(config('bot.vk_bot').'/activate', [
                'form_params' => [
                    'code' => $code,
                    'class_id' => Auth::User()->id,
                    'key' => config('bot.vk_bot_key')
                ]
            ]);
            $result = json_decode($res->getBody()->getContents());
            if ($result->state == "ok")
            {

                $user = User::findOrFail(Auth::User()->id);
                $dublicate = $user->vk_id != null;
                $user->vk_id = intval($result->user_id);
                $user->save();
                if (!$dublicate)
                {
                    CoinTransaction::register($user->id, 5, 'Регистрация в боте');
                }
                return redirect('/activate/success');
            }
            else {
                return redirect()->back()->withErrors(array('code' => 'Проверьте правильность кода...'));
            }
        }
        catch (\Exception $e)
        {
            return redirect()->back()->withErrors(array('code' => 'Ошибка связи с сервисом...'));
        }

    }

    public function successView()
    {
        return view('bot.success');
    }

    public function saveFeedback(Request $request)
    {
        $mark = $request->mark;
        $key = $request->key;
        $comment = $request->comment;
        $user_id = $request->id;

        $user = User::findOrFail($user_id);

        if ($key != config('bot.vk_bot_key')) {
            Log::info("wrong key");
            return "error";
        }


        $record = new FeedbackRecord();
        $record->mark = $mark;
        $record->comment = $comment;
        $record->user_id = $user->id;
        $record->save();

        if ($mark < 6)
        {
            $admin = User::findOrFail(1);
            $when = Carbon::now()->addSeconds(1);
            $admin->notify((new NewExtremeFeedback($record))->delay($when));

        }

        CoinTransaction::register($user->id, 1, 'Обратная связь после занятий');

        return "ok";
    }


}
