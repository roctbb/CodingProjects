<?php

namespace App\Http\Controllers;

use App\Article;
use App\CoinTransaction;
use App\CourseCategory;
use App\User;
use App\Course;
use Illuminate\Http\Request;

class CourseCategoriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin')->except('index', 'details');
    }

    public function index()
    {
        $courses = Course::where('mode', 'zoom')->where('state', 'draft')->get();
        return view('rocket.landing.courses.index', compact('courses'));
    }

    public function details($id)
    {
        $course = Course::findOrFail($id);
        return view('rocket.landing.courses.details', compact('course'));
    }

    public function createView()
    {
        return view('categories.create');
    }

    public function editView($id)
    {
        $category = CourseCategory::findOrFail($id);
        return view('categories.edit', compact('category'));
    }

    public function edit($id, Request $request)
    {
        $category = CourseCategory::findOrFail($id);

        $this->validate($request, [
            'title' => 'required|string',
            'short_description' => 'required|string',
            'description' => 'required|string',
            'small_image_url' => 'nullable|string',
            'card_image_url' => 'nullable|string',
            'head_image_url' => 'nullable|string',
            'video_url' => 'nullable|string',
        ]);

        $category->title = $request->title;
        $category->description = $request->description;
        $category->short_description = $request->short_description;
        $category->small_image_url = $request->small_image_url;
        $category->head_image_url = $request->head_image_url;
        $category->card_image_url = $request->card_image_url;
        $category->video_url = $request->video_url;

        $category->save();

        return redirect('/categories/' . $category->id);

    }


    public function create(Request $request)
    {
        $category = new CourseCategory();

        $this->validate($request, [
            'title' => 'required|string',
            'short_description' => 'required|string',
            'description' => 'required|string',
            'small_image_url' => 'nullable|string',
            'card_image_url' => 'nullable|string',
            'head_image_url' => 'nullable|string',
            'video_url' => 'nullable|string',
        ]);

        $category->title = $request->title;
        $category->description = $request->description;
        $category->short_description = $request->short_description;
        $category->small_image_url = $request->small_image_url;
        $category->head_image_url = $request->head_image_url;
        $category->card_image_url = $request->card_image_url;
        $category->video_url = $request->video_url;

        $category->save();

        return redirect('/categories/' . $category->id);
    }

    public function delete($id, Request $request)
    {
        CourseCategory::findOrFail($id)->delete();

        return redirect('/courses');
    }


    public function category_details($id)
    {
        $category = CourseCategory::findOrFail($id);
        $open_courses = $category->courses->whereIn('mode', ['open', 'paid']);
        $private_courses = $category->courses->whereIn('mode', ['offline', 'zoom']);

        return view('categories.details', compact('category', 'open_courses', 'private_courses'));
    }

    public function start($id)
    {
        $category = CourseCategory::findOrFail($id);
        $category->is_available = true;
        $category->save();

        return redirect('/courses');
    }

    public function stop($id)
    {
        $category = CourseCategory::findOrFail($id);
        $category->is_available = false;
        $category->save();

        return redirect('/courses');
    }
}
