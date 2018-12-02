<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;


class RandomSendInviteEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:send_email {file_name}';

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
        //
        $fileName = $this->argument('file_name');
        $sentList = array();
        $mailer = app(\App\Mailers\AttendeeMailer::class);
        //Get the file
        Excel::load($fileName)->each(function($csvLine) use ($mailer){
            //send mail 
            $attendee = \App\Models\Attendee::where('email', $csvLine->get('email'))->where('event_id', 7)->first();
            $job = new \App\Jobs\SendAttendeeLotteryTicket($attendee);
            $job->handle($mailer);
        });

        // Send email one to one
        



    }
}
