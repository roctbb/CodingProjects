<?php

namespace App\Policies;

use App\User;
use App\GameComments;
use Illuminate\Auth\Access\HandlesAuthorization;

class GameCommentsPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the game comments.
     *
     * @param  \App\User  $user
     * @param  \App\GameComments  $gameComments
     * @return mixed
     */
    public function delete(User $user, GameComments $gameComments)
    {
        return $user->is_teacher || $gameComments->user->id==$user->id;
    }

}
