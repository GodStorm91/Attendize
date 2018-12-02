<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTicket;
use App\Jobs\SendAttendeeInvite;
use App\Jobs\SendAttendeeTicket;
use App\Jobs\SendMessageToAttendees;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventStats;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Order as OrderService;
use App\Models\Ticket;
use Auth;
use Config;
use DB;
use Excel;
use Illuminate\Http\Request;
use Log;
use Mail;
use Omnipay\Omnipay;
use PDF;
use Validator;

class AttendeeController extends MyBaseController
{
    /**
     * Show the attendees list
     *
     * @param Request $request
     * @param $event_id
     * @return View
     */
    public function sendRsvp(Request $request)
    {

        $orderId = $request->get('o');
        $rsvpStatus = $request->get('s');

        //Get the first Order
        $order = \App\Models\Order::where('order_reference', $orderId)->first();
        if ($rsvpStatus == 1){
            // 1 : completed
            $order->order_status_id = 1;
            $order->save();
            return view('Public.ViewEvent.OrderAccepted');
            //Should we send cancel email here
            
        }

        if ($rsvpStatus == 4){
            // 1 : completed
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
            //Should we send cancel email here
            return view('Public.ViewEvent.OrderCancelled');
            
        }
        
    }



}

