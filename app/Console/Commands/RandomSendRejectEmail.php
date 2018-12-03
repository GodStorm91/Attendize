<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;


class RandomSendRejectEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:send_reject {file_name}';

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
        $okList = array();
        $mailer = app(\App\Mailers\AttendeeMailer::class);
        $sentList = array();
        //Get the file
        Excel::load($fileName)->each(function($csvLine) use ($mailer, &$okList){
            //send mail 
            $attendee = \App\Models\Attendee::find($csvLine->get('id'));
            $job = new \App\Jobs\SendAttendeeLotteryMissed($attendee);
            $okList[] = $attendee->id;
            $job->handle($mailer);
        });

    }

    /**
     * Export answers to xls, csv etc.
     *
     * @param Request $request
     * @param $event_id
     * @param string $export_as
     */
    public function exportAttendees($attendees, $export_as = 'xlsx')
    {
        Excel::create('answers-as-of-' . date('d-m-Y-g.i.a'), function ($excel) use ($attendees) {

            $excel->setTitle(trans("Controllers.survey_answers"));

            // Chain the setters
            $excel->setCreator(config('attendize.app_name'))
                ->setCompany(config('attendize.app_name'));

            $excel->sheet('survey_answers_sheet_', function ($sheet) use ($attendees) {

                $sheet->fromArray($attendees, null, 'A1', false, false);

                // Set gray background on first row
                $sheet->row(1, function ($row) {
                    $row->setBackground('#f5f5f5');
                });
            });
        })->store($export_as);
    }

}
