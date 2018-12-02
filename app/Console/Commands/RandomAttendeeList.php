<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Event;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Attendee;


class RandomAttendeeList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:random_list {file_name}';

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
        $targetIT = 178;
        $targetStudent = 22; // Student + IT Others
        $targetOthers = 22; // Others
        $allowedList = ["Data Scientist Engineer","Software Engineer", "UI/UX Engineer","Project Manager", "AI Engineer", "Blockchain Engineer"];
        
        $studenList = ["university", "sinh viên"];
        $otherITList = ["Others (IT related)"];
        $othersList = ["Others (Non IT related)"];



        $fileName = $this->argument('file_name');

        $event = Event::find(7);

        $whiteList = array();
        //Random
        Excel::load("whitelist.csv")->each(function($csvLine) use (&$whiteList){
            //send mail 
            $whiteList[] = $csvLine->get("id");
        });

        $itList = array();
        //Random
        Excel::load("tech.csv")->each(function($csvLine) use (&$itList){
            //send mail 
            $this->info("csv" . $csvLine->get("id"));
            $itList[] = $csvLine->get("id");
        });

        $studentList = array();
        //Random
        Excel::load('student.csv')->each(function($csvLine) use (&$studentList){
            //send mail 
            $this->info("student:" . $csvLine->get("id"));
            $studentList[] = $csvLine->get("id");
        });

        //Random
        $blacklist = array();
        Excel::load('blacklist.csv')->each(function($csvLine) use (&$blacklist){
            //send mail 
            // $this->info("student:" . $csvLine->get("id"));
            $blacklist[] = $csvLine->get("id");
        });

        $nonIt = array();
        //Random
        Excel::load('nontech.csv')->each(function($csvLine) use (&$nonIt){
            //send mail 
            $nonIt[] = $csvLine->get("id");
        });


        // Get IT Attendees
        $itAttendees = Attendee::whereIn('id', $itList)->whereNotIn('id', $blacklist)
        ->whereNotIn('id', $whiteList)->with('answers')
        ->get();

        $nonItAttendees = Attendee::whereIn('id', $nonIt)->whereNotIn('id', $blacklist)
        ->whereNotIn('id', $whiteList)->with('answers')
        ->get();

        $studentAttendees = Attendee::whereIn('id', $studentList)->whereNotIn('id', $blacklist)
        ->whereNotIn('id', $whiteList)->with('answers')
        ->get();

        $this->info("student list:" . count($studentAttendees->all()));

        $whitelistAttendees = Attendee::whereIn('id', $whiteList)
        ->whereNotIn('id', $blacklist)->with('answers')
        ->get();


        $insertedEmails = $whitelistAttendees->pluck('email');
        $insertedIds = $whitelistAttendees->pluck('id');


        $results = $whitelistAttendees;

        $itShuffled = $itAttendees->shuffle();
        $studentShuffled = $studentAttendees->shuffle();
        $nonItShuffled = $nonItAttendees->shuffle();

        $allList = $insertedIds->all();

        $cnt = 0;
        $insertedCurrent = array();
        $this->info("it:" . count($whiteList));
        while ( count($insertedCurrent) < $targetIT){
            # code...
            if ($cnt > count($itShuffled)) break;

            $attendee = $itShuffled->get($cnt);

            // $this->info("run:" . $cnt);

            if (empty($attendee->email)) {
                $cnt++;
                continue;
            }

            if (!in_array($attendee->email, $insertedCurrent) && !in_array($attendee->email, $allList)){
                $this->info("add:". $attendee->email);
                $insertedCurrent[] = $attendee->email;
                $results->push($attendee);
                $allList[] = $attendee->id;
            }
            $cnt++;
        }

        // Studdent
        $insertedCurrent = array();
        $cnt = 0;
        $this->info("student:" . count($studentAttendees->all()));
        while ( count($insertedCurrent) < $targetStudent) {
            # code...
            if ($cnt > count($studentShuffled)) break;
            $attendee = $studentShuffled->get($cnt);
            $this->info("run:" . $cnt);
            if (!in_array($attendee->email, $insertedCurrent) && !in_array($attendee->email, $allList)){
                $this->info("add:". $attendee->email);
                $insertedCurrent[] = $attendee->email;
                $allList[] = $attendee->id;
                $results->push($attendee);
            }
            $cnt++;
        }

        // Nontech
        $insertedCurrent = array();
        $cnt = 0;
        while ( count($insertedCurrent) < $targetOthers) {
            # code...
            if ($cnt > count($nonItShuffled)) break;
            $attendee = $nonItShuffled->get($cnt);
            $this->info("run:" . $cnt);
            if (!in_array($attendee->email, $insertedCurrent) && !in_array($attendee->email, $allList)){
                $this->info("add:". $attendee->email);
                $insertedCurrent[] = $attendee->email;
                $allList[] = $attendee->id;
                $results->push($attendee);
            }
            $cnt++;
        }

        //Output to file

        $this->exportAttendees($results);

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
