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
            $order->save();
            //Should we send cancel email here
            return view('Public.ViewEvent.OrderCancelled');
            
        }
        
    }



}

