<?php
/**
 * Created by PhpStorm.
 * User: AlexNerru
 * Date: 03.09.2017
 * Time: 23:22
 */

namespace App\Http\Controllers;

use App\Article;
use App\ArticleTag;
use App\CoinTransaction;
use App\ForumComment;
use App\ForumPost;
use App\ForumTag;
use App\ForumThread;
use App\ForumVote;
use App\Notifications\NewForumAnswer;
use App\Project;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\Request;
use Auth;
use Carbon\Carbon;


class ArticlesController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth')->except('open_index', 'details');
    }

    public function open_index(Request $request)
    {
        $tag = null;
        if ($request->has('tag') and $request->tag != "") {
            $tag = ArticleTag::where('name', $request->tag)->first();
        }

        $articles = Article::where('is_draft', false)->where('is_approved', true)->orderBy('created_at', 'DESC')->get();

        if ($tag) {
            $articles = $articles->filter(function ($item) use ($tag) {
                return $item->tags->contains($tag);
            });
        }

        $count = $articles->count();
        $page = 1;
        $pages = ceil($count / 5);
        if ($request->has('page')) {
            $page = intval($request->page);
        }

        $articles = $articles->forPage($page, 5);

        return view('articles', compact('articles', 'tag', 'page', 'pages'));
    }

    public function index(Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);

        $tag = null;
        if ($request->has('tag') and $request->tag != "") {
            $tag = ArticleTag::where('name', $request->tag)->first();
        }

        $articles = Article::where('is_draft', false)->where('is_approved', true)->orderBy('created_at', 'DESC')->get();
        $my_articles = Article::where('author_id', $user->id)->orderBy('created_at', 'DESC')->get();
        $draft_articles = Article::where('is_draft', false)->where('is_approved', false)->orderBy('created_at', 'DESC')->get();
        if ($tag) {
            $articles = $articles->filter(function ($item) use ($tag) {
                return $item->tags->contains($tag);
            });
        }
        $count = $articles->count();
        $page = 1;
        $pages = ceil($count / 5);

        if ($request->has('page')) {
            $page = intval($request->page);
        }

        $articles = $articles->forPage($page, 5);

        return view('articles.index', compact('articles', 'tag', 'page', 'pages', 'my_articles', 'draft_articles', 'user'));
    }


    public function createView()
    {
        return view('articles.create');
    }

    public function editView($id)
    {
        $article = Article::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);

        if ($article->author_id != $user->id and $user->role != 'admin') abort(503);

        return view('articles.edit', compact('article'));
    }

    public function edit($id, Request $request)
    {
        $article = Article::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);

        if ($article->author_id != $user->id and $user->role != 'admin') abort(503);


        $this->validate($request, [
            'name' => 'required|string',
            'text' => 'required|string',
            'image' => 'required|string',
            'anounce' => 'required|string',
        ]);

        $article->text = clean($request->text, 'youtube');
        $article->anounce = clean($request->anounce);
        $article->name = $request->name;
        $article->image = $request->image;

        if ($user->role != 'admin') {
            $article->is_draft = true;
            $article->is_approved = false;
        }

        foreach ($article->tags as $tag) {
            $article->tags()->detach($tag->id);
        }
        $parts = explode(';', mb_strtolower($request->tags));
        foreach ($parts as $tag) {
            if ($tag == '') continue;
            $article->attachTag($tag);
        }
        $article->save();

        return redirect('/insider/articles/' );

    }


    public function create(Request $request)
    {
        $user = User::findOrFail(Auth::User()->id);

        $this->validate($request, [
            'name' => 'required|string',
            'text' => 'required|string',
            'image' => 'required|string',
            'anounce' => 'required|string',

        ]);
        $user = User::findOrFail(Auth::User()->id);

        $article = new Article();
        $article->text = clean($request->text, 'youtube');
        $article->anounce = clean($request->anounce);
        $article->name = $request->name;
        $article->image = $request->image;
        $article->author_id = $user->id;
        $article->is_draft = true;
        if ($user->role == 'admin') {
            $article->is_approved = true;
        }
        $article->save();

        $parts = explode(';', mb_strtolower($request->tags));

        foreach ($parts as $tag) {
            if ($tag == '') continue;
            $article->attachTag($tag);
        }

        return redirect('/insider/articles/'.$article->id);
    }

    public function delete($id, Request $request)
    {
        $article = Article::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);

        if ($article->user_id != $user->id and $user->role != 'admin') abort(503);
        $article->delete();

        return redirect('/insider/articles');
    }


    public function details($id)
    {
        $article = Article::findOrFail($id);
        $article->visits += 1;
        $article->save();
        return view('articles.details', compact('article'));
    }

    public function publish($id)
    {
        $article = Article::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);
        if ($user->role != 'admin') abort(503);
        $article->is_draft = false;
        $article->is_approved = true;
        $article->save();

        if (!$article->is_paid)
        {
            CoinTransaction::register($article->author->id, 25, "Article #".$article->id);
        }


        return redirect('/insider/articles');
    }

    public function upvote($id)
    {
        $article = \App\Article::findOrFail($id);

        $article->vote(1);
        return back();
    }

    public function downvote($id)
    {
        $article = \App\Article::findOrFail($id);

        $article->vote(-1);
        return back();
    }

    public function delete_comment($id)
    {
        $comment = \App\ArticleComments::find($id);
        if (\Auth::id() == $comment->user->id || \Auth::user()->role == "admin")
        {
            $comment->delete();
        }
        return back();
    }

    public function comment($id, Request $request)
    {
        \App\ArticleComments::create([
            "user_id" => \Auth::id(),
            "article_id" => $id,
            "comment" => $request->comment
        ]);
        
        return redirect('/insider/articles/'.$id);
    }

    public function draft($id)
    {
        $article = Article::findOrFail($id);
        $user = User::findOrFail(Auth::User()->id);
        if ($user->role != 'admin') abort(503);
        $article->is_draft = true;
        $article->save();
        return redirect('/insider/articles');
    }

    public function ask_review($id, Request $request)
    {
        $article = Article::findOrFail($id);
        $article->is_draft = false;
        $article->save();

        $when = \Carbon\Carbon::now()->addSeconds(1);

        foreach (User::where('role', 'admin') as $admin) {
            \Notification::send($admin, (new \App\Notifications\NewArticle($article))->delay($when));
        }
        \Session::flash('message', 'Уведомления отправлены!');
        return redirect('/insider/articles/');

    }

}
