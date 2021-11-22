<div onclick="toggleLike({{$event->id}}, $(this));" style="cursor: pointer;" data-liked="{{$event->hasLiked(Auth::id()) ? 'true' : 'false'}}" data-likes="{{$event->userLikes()->count()}}">
    @if($event->hasLiked(Auth::User()->id))
    <img width="24px" src="{{ url('images/icons/icons8-like-48.png') }}" alt="dislike">
    @else
    <img width="24px" src="{{ url('images/icons/icons8-heart-24.png') }}" alt="like">
    @endif
    <span class="likes1">{{count($event->userLikes)}}</span>
</div>