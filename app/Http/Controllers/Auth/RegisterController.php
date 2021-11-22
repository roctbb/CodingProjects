<?php

namespace App\Http\Controllers\Auth;

use App\Provider;
use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Carbon\Carbon;
use App\Course;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    public function showRegistrationForm(Request $request)
    {
        $to_course = false;
        if ($request->has('course_id'))
        {
            $to_course = true;
        }
        return view('auth.register', compact('to_course'));
    }

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = 'insider/courses';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'name' => 'required|string',
            'school' => 'required|string',
            'grade' => 'required|integer',
            'birthday' => 'required|date|date_format:Y-m-d',
            'image' => 'image|max:1000',
            'g-recaptcha-response' => app('App\Services\Recaptcha')->getValidationString()
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return User
     */
    protected function create($data)
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'email_verified_at' => app('\App\Services\EmailVerify')->getDate()
        ]);
        $user->vk = $data->vk;
        $user->git = $data->git;
        $user->facebook = $data->facebook;
        $user->telegram = $data->telegram;
        $user->hobbies = $data->hobbies;
        $user->interests = $data->interests;
        $user->school = $data->school;
        $user->birthday = Carbon::createFromFormat('Y-m-d', $data->birthday);
        $user->setGrade($data->grade);

        if ($data->hasFile('image')) {
            $extn = '.' . $data->file('image')->guessClientExtension();
            $path = $data->file('image')->storeAs('user_avatars', $user->id . $extn);
            $user->image = $path;
        }

        $user->save();
        return $user;
    }

    public function register(Request $request)
    {
        $is_teacher = false;
        $is_novice = false;
        $course = null;
        $provider = Provider::where('invite', $request->invite)->first();
        if ($provider != null) {
            $is_teacher = true;
        } else {
            if ($request->invite == null || $request->invite == "") {
                $is_novice = true;
            }
            $course = Course::where('invite', $request->invite)->first();
            if ($course == null) {
                $this->make_error_alert('Ошибка!', 'Курс с таким приглашением не найден.');
                return $this->backException();
            }
        }


        $this->validator($request->all())->validate();

        event(new Registered($user = $this->create($request)));
        if ($is_teacher) {
            $user->role = 'teacher';
            $user->provider_id = $provider->id;
            $user->save();
        } else {
            if ($is_novice) {
                $user->role = 'novice';
                $user->save();
            } else {
                $course->students()->attach($user->id);
            }

        }
        $this->guard()->login($user);

        if ($request->has('course_id')) {
            $course = Course::where('id', $request->course_id)->first();
            if ($course == null or $course->mode != 'open') {
                $this->make_error_alert('Ошибка!', 'Вы не можете записаться на приватный курс.');
            } else {
                $course->students()->attach([$user->id => ['is_remote' => false]]);
            }
        }

        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }
}
