<?php
namespace App\Http\Controllers;

use App\Course;
use App\Services\CourseTelegram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CourseNotificationsController extends Controller
{
    private function course(Request $request, int $id): Course
    {
        $course = Course::findOrFail($id);
        abort_unless(app(CourseTelegram::class)->isTeacher($course, $request->user()), 403);
        return $course;
    }

    public function edit(Request $request, int $id)
    {
        $course = $this->course($request, $id);
        return response()->view('courses.notifications', [
            'course' => $course, 'settings' => DB::table('course_notification_settings')->where('course_id', $id)->first(),
            'chats' => DB::table('course_telegram_chats')->where('course_id', $id)->where('bot_key', app(CourseTelegram::class)->botKey())->get(),
            'deliveries' => DB::table('deadline_notification_deliveries')->where('course_id', $id)->orderByDesc('id')->paginate(20),
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, int $id)
    {
        $this->course($request, $id);
        $request->validate(['teachers_enabled' => 'sometimes|boolean', 'chat_enabled' => 'sometimes|boolean']);
        DB::table('course_notification_settings')->updateOrInsert(['course_id' => $id], ['teachers_enabled' => $request->boolean('teachers_enabled'),
            'chat_enabled' => $request->boolean('chat_enabled'), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Настройки уведомлений сохранены.');
    }

    public function link(Request $request, int $id)
    {
        $course = $this->course($request, $id);
        return back()->with('telegram_course_link', app(CourseTelegram::class)->link($course, $request->user()));
    }

    public function chat(Request $request, int $id, int $chat)
    {
        $this->course($request, $id);
        $request->validate(['action' => 'required|in:confirm,unlink']);
        $query = DB::table('course_telegram_chats')->where('id', $chat)->where('course_id', $id)->where('bot_key', app(CourseTelegram::class)->botKey());
        abort_unless((clone $query)->exists(), 404);
        if ($request->input('action') === 'confirm') {
            abort_unless((clone $query)->where('status', 'pending')->where('updated_at', '>', now()->subMinutes(30))->exists(), 422, 'Подключите чат заново: подтверждение устарело.');
            $query->update(['status' => 'active', 'confirmed_at' => now(), 'updated_at' => now()]);
        } else $query->update(['status' => 'disabled', 'updated_at' => now()]);
        return back();
    }

    public function retry(Request $request, int $id, int $delivery)
    {
        $this->course($request, $id);
        $updated = DB::table('deadline_notification_deliveries')->where('id', $delivery)->where('course_id', $id)->whereIn('status', ['uncertain', 'failed'])
            ->update(['status' => 'pending', 'available_at' => now()->utc(), 'last_error' => null, 'updated_at' => now()->utc()]);
        abort_unless($updated, 409);
        return back()->with('status', 'Повторная попытка добавлена в очередь.');
    }

    public function preference(Request $request, int $id)
    {
        $course = Course::findOrFail($id);
        abort_unless($course->students()->where('users.id', $request->user()->id)->exists(), 403);
        $request->validate(['enabled' => 'sometimes|boolean']);
        DB::table('course_notification_preferences')->updateOrInsert(['course_id' => $id, 'user_id' => $request->user()->id],
            ['enabled' => $request->boolean('enabled'), 'created_at' => now(), 'updated_at' => now()]);
        return back()->with('status', 'Личные напоминания обновлены.');
    }
}
