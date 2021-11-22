<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BirthdaySender extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'birthdays';

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
        $data = \Carbon\Carbon::now();
        $students = \App\User::all()->filter(function ($user) use ($data) {
            return $user->birthday != null and $user->birthday->month == $data->month and $user->birthday->day == $data->day;
        });

        foreach ($students as $student) {
            $age = $data->year - $student->birthday->year;
            \App\CoinTransaction::register($student->id, $age, "ДР ".$data->year);
        }
    }
}
