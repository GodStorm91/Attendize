<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;


class CancelAttendees extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cancel:attendee {file_name}';

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
        //Get the file
        Excel::load($fileName)->each(function($csvLine) use ($mailer, &$okList){
            //send mail 
            $attendee = \App\Models\Attendee::find($csvLine->get('id'));
            $this->info("send cancel mail for" . $attendee->id . " " . $attendee->email);
            $job = new \App\Jobs\CancelAttendee($attendee);
            $job->handle($mailer);
            $this->deleteAttendee($attendee->order);
        });

        $ngAttendee = \App\Models\Attendee::whereNotIn('id', $okList)->get();
        // Send email one to one
    }


    public function deleteAttendee($order){
            $order->order_status_id = 4;
            $attendees = $order->attendees;
            if ($attendees) {
                foreach ($attendees as $attendee) {
                    $attendee->ticket->decrement('quantity_sold');
                    $attendee->ticket->decrement('sales_volume', $attendee->ticket->price);
                    $order->event->decrement('sales_volume', $attendee->ticket->price);
                    $order->decrement('amount', $attendee->ticket->price);
                    $attendee->is_cancelled = 1;
                    $attendee->save();

                    $eventStats = EventStats::where('event_id', $attendee->event_id)->where('date', $attendee->created_at->format('Y-m-d'))->first();
                    if($eventStats){
                        $eventStats->decrement('tickets_sold',  1);
                        $eventStats->decrement('sales_volume',  $attendee->ticket->price);
                    }
                }
            }
            $order->save();
    }
}
