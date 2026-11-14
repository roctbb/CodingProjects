<?php

namespace App\Http\Controllers\Auth;

use App\Provider;
use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Registered;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users',
                function ($attribute, $value, $fail) {
                    if (str_contains($value, '+')) {
                        $fail('Адрес электронной почты не должен содержать символ "+".');
                    }
                },
            ],
            'password' => 'required|string|min:6|max:72|confirmed',
            'name' => 'required|string|max:255',
            'school' => 'required|string|max:255',
            'grade' => 'required|integer|between:1,12',
            'gender' => ['required', 'string', Rule::in(array_keys(User::learningAvatarGenders()))],
            'birthday' => 'required|date|date_format:Y-m-d',
            'git' => 'nullable|string|max:255',
            'telegram' => 'nullable|string|max:255',
            'hobbies' => 'nullable|string|max:10000',
            'interests' => 'nullable|string|max:10000',
            'image' => 'image|max:10240',
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
            'gender' => $data['gender'],
            'email' => ltrim(rtrim(mb_strtolower($data['email']))),
            'password' => bcrypt($data['password']),
            'email_verified_at' => app('\App\Services\EmailVerify')->getDate()
        ]);
        $user->git = $data->git;
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
        $this->validate($request, [
            'invite' => 'required|string|max:255',
        ]);

        $is_teacher = false;
        $invitation = null;
        $course = null;
        $provider = Provider::where('invite', $request->invite)->first();
        if ($provider != null) {
            $is_teacher = true;
        } else {
            $invitation = app(\App\Services\CourseEnrollment::class)->invitation($request->invite);
            $course = $invitation['course'] ?? null;
            if ($course == null) {
                throw ValidationException::withMessages(['invite' => 'Курс с таким кодом приглашения не найден. Проверьте код у преподавателя.']);
            }
        }


        $this->validator($request->all())->validate();

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $is_teacher, $provider, $course, $invitation) {
        $user = $this->create($request);
        if ($is_teacher) {
            $user->role = 'teacher';
            $user->provider_id = $provider->id;
            $user->save();
        } else {
            app(\App\Services\CourseEnrollment::class)->enroll($course, $user, $invitation['remote'] ?? false);

        }

        if ($request->has('course_id')) {
            $course = Course::where('id', $request->course_id)->first();
            if ($course == null or $course->mode != 'open') {
                $this->make_error_alert('Ошибка!', 'Вы не можете записаться на приватный курс.');
            } else {
                app(\App\Services\CourseEnrollment::class)->enroll($course, $user);
            }
        }

        return $user;
        });
        event(new Registered($user));
        $this->guard()->login($user);
        return $this->registered($request, $user)
            ?: redirect($this->redirectPath());
    }
}
