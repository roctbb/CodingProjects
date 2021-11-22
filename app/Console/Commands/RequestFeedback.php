<?php

namespace App\Console\Commands;

use App\Course;
use App\DetailedFeedback;
use Illuminate\Console\Command;
use Log;

class RequestFeedback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'feedback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $courses = Course::where('state', 'started')->where('weekdays', '!=', '')->get()->filter(function ($course) {
            $current = \Carbon\Carbon::now()->dayOfWeek;
            if ($current == 0) $current = 7;
            return in_array(strval($current), explode(';', $course->weekdays));
        });

        $students = [];
        foreach ($courses as $course) {
            foreach ($course->students as $student) {
                $record = DetailedFeedback::getRecord($course, $student);

                if (!array_key_exists($student->id, $students)) {
                    $students[$student->id] = $record->access_key;

                    $when = \Carbon\Carbon::now()->addSeconds(1);
                    $student->notify((new \App\Notifications\Feedback($record->access_key))->delay($when));
                } else {
                    $key = $students[$student->id];
                    $record->access_key = $key;
                    $record->save();
                }
            }
        }
        return True;

    }
}
