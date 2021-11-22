<gk-votes 
        :downvotes="{{$game->downvotes()-$game->hasDownvoted(\Auth::id())}}" 
        :upvotes="{{$game->upvotes()-$game->hasUpvoted(\Auth::id())}}"
        :upvoted="{{$game->hasUpvoted(\Auth::id())?'1':'0'}}"
        :downvoted="{{$game->hasDownvoted(\Auth::id())?'1':'0'}}"
        :urls="{upvote: '/insider/games/{{$game->id}}/upvote', downvote: '/insider/games/{{$game->id}}/downvote'}"
        ></gk-votes>

