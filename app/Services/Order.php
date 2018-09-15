<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Order as OrderModel;
use App\Models\Affiliate;
use App\Models\Attendee;
use App\Models\EventStats;
use App\Models\OrderItem;
use App\Models\QuestionAnswer;
use App\Models\Ticket;
use DB;


class Order
{
    /**
     * @var float
     */
    private $orderTotal;

    /**
     * @var float
     */
    private $totalBookingFee;

    /**
     * @var Event
     */
    private $event;

    /**
     * @var float
     */
    public $orderTotalWithBookingFee;

    /**
     * @var float
     */
    public $taxAmount;

    /**
     * @var float
     */
    public $grandTotal;

    /**
     * Order constructor.
     * @param $orderTotal
     * @param $totalBookingFee
     * @param $event
     */
    public function __construct($orderTotal, $totalBookingFee, $event)
    {

        $this->orderTotal = $orderTotal;
        $this->totalBookingFee = $totalBookingFee;
        $this->event = $event;
    }

    /**
     * Create new order
     *
     * @param $ticket_order
     * @param $request_data
     * @param $account_id
     * @param $order_status
     * @return OrderModel
     */
    public function newOrder($ticket_order, $request_data, $order_status)
    {

        $order = new OrderModel();
        if (isset($ticket_order['transaction_id'])) {
            $order->transaction_id = $ticket_order['transaction_id'][0];
        }
        if ($ticket_order['order_requires_payment'] && !isset($request_data['pay_offline'])) {
            $order->payment_gateway_id = $ticket_order['payment_gateway']->id;
        }
        $order->first_name = $request_data['order_first_name'];
        $order->last_name = $request_data['order_last_name'];
        $order->email = $request_data['order_email'];
        $order->order_status_id = $order_status;
        $order->amount = $ticket_order['order_total'];
        $order->booking_fee = $ticket_order['booking_fee'];
        $order->organiser_booking_fee = $ticket_order['organiser_booking_fee'];
        $order->discount = 0.00;
        $order->account_id = $this->event->account->id;
        $order->event_id = $ticket_order['event_id'];
        $order->is_payment_received = isset($request_data['pay_offline']) ? 0 : 1;

        // Calculating grand total including tax
        $this->calculateFinalCosts();
        $order->taxamt = $this->getTaxAmount();

        return $order;
    }

    /**
     * Update the event sales volume
     *
     * @param $organiser_booking_fee
     */
    public function updateSaleVolumes($organiser_booking_fee)
    {
        $this->event->increment('sales_volume', $this->getGrandTotal());
        $this->event->increment('organiser_fees_volume', $organiser_booking_fee);
    }

    /**
     * Update affiliates stats
     *
     * @param $ticket_order
     * @param $sales_volume
     */
    public function updateAffiliateStats($ticket_order, $sales_volume)
    {
        if ($ticket_order['affiliate_referral']) {
            $affiliate = Affiliate::where('name', '=', $ticket_order['affiliate_referral'])
                ->where('event_id', '=', $this->event->id)->first();
            $affiliate->increment('sales_volume', $sales_volume);
            $affiliate->increment('tickets_sold', $ticket_order['total_ticket_quantity']);
        }
    }

    /**
     * Update the event stats
     *
     * @param $ticket_order
     * @param $sales_volume
     * @param $organiser_fees_volume
     */
    public function updateEventStats($ticket_order, $sales_volume, $organiser_fees_volume)
    {

        $event_stats = EventStats::updateOrCreate([
            'event_id' => $this->event->id,
            'date' => DB::raw('CURRENT_DATE'),
        ]);
        $event_stats->increment('tickets_sold', $ticket_order['total_ticket_quantity']);

        if ($ticket_order['order_requires_payment']) {
            $event_stats->increment('sales_volume', $sales_volume);
            $event_stats->increment('organiser_fees_volume', $organiser_fees_volume);
        }
    }

    /**
     * Add the attendees
     *
     * @param $order
     * @param $ticket_order
     * @param $request_data
     */
    public function addAttendees($order, $ticket_order, $request_data)
    {
        $attendee_increment = 1;
        $ticket_questions = isset($request_data['ticket_holder_questions']) ? $request_data['ticket_holder_questions'] : [];

        foreach ($ticket_order['tickets'] as $attendee_details) {

            $this->updateTicket($attendee_details);

            $this->insertOrderItems($order, $attendee_details);

            for ($i = 0; $i < $attendee_details['qty']; $i++) {
                $attendee = new Attendee();
                $attendee->first_name = $request_data["ticket_holder_first_name"][$i][$attendee_details['ticket']['id']];
                $attendee->last_name = $request_data["ticket_holder_last_name"][$i][$attendee_details['ticket']['id']];
                $attendee->email = $request_data["ticket_holder_email"][$i][$attendee_details['ticket']['id']];
                $attendee->event_id = $this->event->id;
                $attendee->order_id = $order->id;
                $attendee->ticket_id = $attendee_details['ticket']['id'];
                $attendee->account_id = $this->event->account->id;
                $attendee->reference_index = $attendee_increment;
                $attendee->save();

                // Save the attendee's questions
                foreach ($attendee_details['ticket']->questions as $question) {


                    $ticket_answer = isset($ticket_questions[$attendee_details['ticket']->id][$i][$question->id]) ? $ticket_questions[$attendee_details['ticket']->id][$i][$question->id] : null;

                    if (is_null($ticket_answer)) {
                        continue;
                    }

                    /*
                     * If there are multiple answers to a question then join them with a comma
                     * and treat them as a single answer.
                     */
                    $ticket_answer = is_array($ticket_answer) ? implode(', ', $ticket_answer) : $ticket_answer;

                    if (!empty($ticket_answer)) {
                        QuestionAnswer::create([
                            'answer_text' => $ticket_answer,
                            'attendee_id' => $attendee->id,
                            'event_id' => $this->event->id,
                            'account_id' => $this->event->account->id,
                            'question_id' => $question->id
                        ]);

                    }
                }
            }

            /* Keep track of total number of attendees */
            $attendee_increment++;
        }
    }

    /**
     *
     * @param $attendee_details
     */
    public function updateTicket($attendee_details)
    {
        $ticket = Ticket::findOrFail($attendee_details['ticket']['id']);
        $ticket->increment('quantity_sold', $attendee_details['qty']);
        $ticket->increment('sales_volume', ($attendee_details['ticket']['price'] * $attendee_details['qty']));
        $ticket->increment('organiser_fees_volume', ($attendee_details['ticket']['organiser_booking_fee'] * $attendee_details['qty']));
    }

    /**
     * @param $order
     * @param $attendee_details
     */
    public function insertOrderItems($order, $attendee_details)
    {
        $orderItem = new OrderItem();
        $orderItem->title = $attendee_details['ticket']['title'];
        $orderItem->quantity = $attendee_details['qty'];
        $orderItem->order_id = $order->id;
        $orderItem->unit_price = $attendee_details['ticket']['price'];
        $orderItem->unit_booking_fee = $attendee_details['ticket']['booking_fee'] + $attendee_details['ticket']['organiser_booking_fee'];
        $orderItem->save();
    }

    /**
     * Calculates the final costs for an event and sets the various totals
     */
    public function calculateFinalCosts()
    {
        $this->orderTotalWithBookingFee = $this->orderTotal + $this->totalBookingFee;

        if ($this->event->organiser->charge_tax == 1) {
            $this->taxAmount = ($this->orderTotalWithBookingFee * $this->event->organiser->tax_value) / 100;
        } else {
            $this->taxAmount = 0;
        }

        $this->grandTotal = $this->orderTotalWithBookingFee + $this->taxAmount;
    }

    /**
     * @param bool $currencyFormatted
     * @return float|string
     */
    public function getOrderTotalWithBookingFee($currencyFormatted = false)
    {

        if ($currencyFormatted == false) {
            return number_format($this->orderTotalWithBookingFee, 2, '.', '');
        }

        return money($this->orderTotalWithBookingFee, $this->event->currency);
    }

    /**
     * @param bool $currencyFormatted
     * @return float|string
     */
    public function getTaxAmount($currencyFormatted = false)
    {

        if ($currencyFormatted == false) {
            return number_format($this->taxAmount, 2, '.', '');
        }

        return money($this->taxAmount, $this->event->currency);
    }

    /**
     * @param bool $currencyFormatted
     * @return float|string
     */
    public function getGrandTotal($currencyFormatted = false)
    {

        if ($currencyFormatted == false) {
            return number_format($this->grandTotal, 2, '.', '');
        }

        return money($this->grandTotal, $this->event->currency);

    }

    /**
     * @return string
     */
    public function getVatFormattedInBrackets()
    {
        return "(+" . $this->getTaxAmount(true) . " " . $this->event->organiser->tax_name . ")";
    }

}
