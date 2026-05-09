<?php

namespace App;

use App\Notifications\NewCoinTransaction;
use Carbon\Carbon;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class CoinTransaction extends Authenticatable
{
    use Notifiable;

    protected $table = 'coin_transactions';

    private $notificationText = null;
    private $notificationType = 'success';
    private $notificationIcon = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */


    public function user()
    {
        return $this->belongsTo('App\User', 'user_id', 'id');
    }

    public function displayComment()
    {
        $comment = trim((string) $this->comment);

        if ($comment === '') {
            return 'Операция с монетами';
        }

        if (preg_match('/^Task #(\d+)$/', $comment, $matches)) {
            return 'Награда за задачу #' . $matches[1];
        }

        if (preg_match('/^Good #(\d+)$/', $comment, $matches)) {
            return 'Покупка товара #' . $matches[1];
        }

        if (preg_match('/^Auction bid Good #(\d+)$/', $comment, $matches)) {
            return 'Ставка на аукцион #' . $matches[1];
        }

        if (preg_match('/^Auction refund Good #(\d+)$/', $comment, $matches)) {
            return 'Возврат ставки #' . $matches[1];
        }

        if (preg_match('/^Auction order cancel Good #(\d+)$/', $comment, $matches)) {
            return 'Возврат за отмену заказа #' . $matches[1];
        }

        if (preg_match('/^XP booster Solution #(\d+)$/', $comment, $matches)) {
            return 'XP-бустер решения #' . $matches[1];
        }

        if (preg_match('/^GeekPaste extra attempt Task #(\d+)$/', $comment, $matches)) {
            return 'Дополнительная попытка GeekPaste для задачи #' . $matches[1];
        }

        if (preg_match('/^Custom title User #(\d+)$/', $comment, $matches)) {
            return 'Кастомное звание в профиле';
        }

        if (preg_match('/^Avatar frame ([a-z0-9_-]+) User #(\d+)$/', $comment, $matches)) {
            $frames = User::avatarFrames();
            $frameName = $frames[$matches[1]]['name'] ?? null;

            return $frameName ? 'Рамка аватарки "' . $frameName . '"' : 'Рамка аватарки';
        }

        if (preg_match('/^Early access Lesson #(\d+) Course #(\d+)$/', $comment, $matches)) {
            return 'Ранний доступ к уроку #' . $matches[1];
        }

        if (preg_match('/^Rank bonus #(\d+)$/', $comment, $matches)) {
            $rank = Rank::find($matches[1]);
            return $rank ? 'Бонус за звание "' . $rank->name . '"' : 'Бонус за новое звание';
        }

        if (preg_match('/^ДР (\d+)$/u', $comment, $matches)) {
            return 'День рождения ' . $matches[1];
        }

        if (preg_match('/^Лепрекон (\d{4}-\d{2}-\d{2})$/u', $comment, $matches)) {
            return 'Лепрекон принес 3 монеты';
        }

        return $comment;
    }

    public function withNotification($text = null, $type = 'success', $icon = null)
    {
        $this->notificationText = $text;
        $this->notificationType = $type;
        $this->notificationIcon = $icon;

        return $this;
    }

    public function notifyUser()
    {
        $when = Carbon::now()->addSeconds(1);
        $this->user->notify((new NewCoinTransaction(
            $this,
            $this->notificationText,
            $this->notificationType,
            $this->notificationIcon
        ))->delay($when));
    }

    public static function register($user_id, $amount, $comment, $notificationText = null, $notificationType = 'success', $notificationIcon = null)
    {
        $transaction = new CoinTransaction();
        $transaction->user_id = $user_id;
        $transaction->price = $amount;
        $transaction->comment = $comment;
        $transaction->save();
        $transaction->withNotification($notificationText, $notificationType, $notificationIcon);
        $transaction->notifyUser();

        return $transaction;
    }

    public static function registerOnce($user_id, $amount, $comment, $notificationText = null, $notificationType = 'success', $notificationIcon = null)
    {
        $transaction = CoinTransaction::where('user_id', $user_id)
            ->where('comment', $comment)
            ->first();

        if ($transaction) {
            return $transaction;
        }

        return static::register($user_id, $amount, $comment, $notificationText, $notificationType, $notificationIcon);
    }

    public static function existsWithComment($comment)
    {
        return static::where('comment', $comment)->exists();
    }

}
