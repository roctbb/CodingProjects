<?php

namespace App\Console\Commands;

use App\Course;
use App\DetailedFeedback;
use Illuminate\Console\Command;
use Log;

class LowerEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lower_emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lower emails in database';

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

        foreach (\App\User::all() as $user) {
            $user->email = ltrim(rtrim(mb_strtolower($user->email)));
            $user->save();
        }
    }
}
