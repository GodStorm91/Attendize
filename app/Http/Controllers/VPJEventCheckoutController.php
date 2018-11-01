<?php

namespace App\Http\Controllers;

use App\Events\OrderCompletedEvent;
use App\Models\AccountPaymentGateway;
use App\Models\Event;
use App\Models\Order;
use App\Models\ReservedTickets;
use App\Models\Ticket;
use App\Services\Payment as PaymentService;
use App\Services\Order as OrderService;
use Carbon\Carbon;
use Cookie;
use DB;
use Illuminate\Http\Request;
use Log;
use Omnipay;
use PDF;
use PhpSpec\Exception\Exception;
use Validator;

class VPJEventCheckoutController extends Controller
{
    /**
     * Is the checkout in an embedded Iframe?
     *
     * @var bool
     */
    protected $is_embedded;

    /**
     * EventCheckoutController constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        /*
         * See if the checkout is being called from an embedded iframe.
         */
        $this->is_embedded = $request->get('is_embedded') == '1';
    }

    /**
     * Validate a ticket request. If successful reserve the tickets and redirect to checkout
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function postValidateTickets(Request $request, $event_id)
    {
        /*
         * Order expires after X min
         */
        $order_expires_time = Carbon::now()->addMinutes(config('attendize.checkout_timeout_after'));

        $event = Event::findOrFail($event_id);

        if (!$request->has('tickets')) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tickets selected',
            ]);
        }

        $ticket_ids = $request->get('tickets');

        /*
         * Remove any tickets the user has reserved
         */
        ReservedTickets::where('session_id', '=', session()->getId())->delete();

        /*
         * Go though the selected tickets and check if they're available
         * , tot up the price and reserve them to prevent over selling.
         */

        $validation_rules = [];
        $validation_messages = [];
        $tickets = [];
        $order_total = 0;
        $total_ticket_quantity = 0;
        $booking_fee = 0;
        $organiser_booking_fee = 0;
        $quantity_available_validation_rules = [];

        foreach ($ticket_ids as $ticket_id) {
            $current_ticket_quantity = (int)$request->get('ticket_' . $ticket_id);

            if ($current_ticket_quantity < 1) {
                continue;
            }

            $total_ticket_quantity = $total_ticket_quantity + $current_ticket_quantity;

            $ticket = Ticket::find($ticket_id);

            $ticket_quantity_remaining = $ticket->quantity_remaining;


            $max_per_person = min($ticket_quantity_remaining, $ticket->max_per_person);

            $quantity_available_validation_rules['ticket_' . $ticket_id] = [
                'numeric',
                'min:' . $ticket->min_per_person,
                'max:' . $max_per_person
            ];

            $quantity_available_validation_messages = [
                'ticket_' . $ticket_id . '.max' => 'The maximum number of tickets you can register is ' . $ticket_quantity_remaining,
                'ticket_' . $ticket_id . '.min' => 'You must select at least ' . $ticket->min_per_person . ' tickets.',
            ];

            $validator = Validator::make(['ticket_' . $ticket_id => (int)$request->get('ticket_' . $ticket_id)],
                $quantity_available_validation_rules, $quantity_available_validation_messages);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'messages' => $validator->messages()->toArray(),
                ]);
            }

            $order_total = $order_total + ($current_ticket_quantity * $ticket->price);
            $booking_fee = $booking_fee + ($current_ticket_quantity * $ticket->booking_fee);
            $organiser_booking_fee = $organiser_booking_fee + ($current_ticket_quantity * $ticket->organiser_booking_fee);

            $tickets[] = [
                'ticket' => $ticket,
                'qty' => $current_ticket_quantity,
                'price' => ($current_ticket_quantity * $ticket->price),
                'booking_fee' => ($current_ticket_quantity * $ticket->booking_fee),
                'organiser_booking_fee' => ($current_ticket_quantity * $ticket->organiser_booking_fee),
                'full_price' => $ticket->price + $ticket->total_booking_fee,
            ];

            /*
             * Reserve the tickets for X amount of minutes
             */
            $reservedTickets = new ReservedTickets();
            $reservedTickets->ticket_id = $ticket_id;
            $reservedTickets->event_id = $event_id;
            $reservedTickets->quantity_reserved = $current_ticket_quantity;
            $reservedTickets->expires = $order_expires_time;
            $reservedTickets->session_id = session()->getId();
            $reservedTickets->save();

            for ($i = 0; $i < $current_ticket_quantity; $i++) {
                /*
                 * Create our validation rules here
                 */
                $validation_rules['ticket_holder_first_name.' . $i . '.' . $ticket_id] = ['required'];
                $validation_rules['ticket_holder_last_name.' . $i . '.' . $ticket_id] = ['required'];
                $validation_rules['ticket_holder_email.' . $i . '.' . $ticket_id] = ['required', 'email'];

                $validation_messages['ticket_holder_first_name.' . $i . '.' . $ticket_id . '.required'] = 'Ticket holder ' . ($i + 1) . '\'s first name is required';
                $validation_messages['ticket_holder_last_name.' . $i . '.' . $ticket_id . '.required'] = 'Ticket holder ' . ($i + 1) . '\'s last name is required';
                $validation_messages['ticket_holder_email.' . $i . '.' . $ticket_id . '.required'] = 'Ticket holder ' . ($i + 1) . '\'s email is required';
                $validation_messages['ticket_holder_email.' . $i . '.' . $ticket_id . '.email'] = 'Ticket holder ' . ($i + 1) . '\'s email appears to be invalid';

                /*
                 * Validation rules for custom questions
                 */
                foreach ($ticket->questions as $question) {

                    if ($question->is_required && $question->is_enabled) {
                        $validation_rules['ticket_holder_questions.' . $ticket_id . '.' . $i . '.' . $question->id] = ['required'];
                        $validation_messages['ticket_holder_questions.' . $ticket_id . '.' . $i . '.' . $question->id . '.required'] = "This question is required";
                    }

                }

            }

        }

        if (empty($tickets)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No tickets selected.',
            ]);
        }

        if (config('attendize.enable_dummy_payment_gateway') == TRUE) {
            $activeAccountPaymentGateway = new AccountPaymentGateway();
            $activeAccountPaymentGateway->fill(['payment_gateway_id' => config('attendize.payment_gateway_dummy')]);
            $paymentGateway = $activeAccountPaymentGateway;
        } else {
            $activeAccountPaymentGateway = count($event->account->active_payment_gateway) ? $event->account->active_payment_gateway : false;
            $paymentGateway = count($event->account->active_payment_gateway) ? $event->account->active_payment_gateway->payment_gateway : false;
        }

        /*
         * The 'ticket_order_{event_id}' session stores everything we need to complete the transaction.
         */
        session()->put('ticket_order_' . $event->id, [
            'validation_rules' => $validation_rules,
            'validation_messages' => $validation_messages,
            'event_id' => $event->id,
            'tickets' => $tickets,
            'total_ticket_quantity' => $total_ticket_quantity,
            'order_started' => time(),
            'expires' => $order_expires_time,
            'reserved_tickets_id' => $reservedTickets->id,
            'order_total' => $order_total,
            'booking_fee' => $booking_fee,
            'organiser_booking_fee' => $organiser_booking_fee,
            'total_booking_fee' => $booking_fee + $organiser_booking_fee,
            'order_requires_payment' => (ceil($order_total) == 0) ? false : true,
            'account_id' => $event->account->id,
            'affiliate_referral' => Cookie::get('affiliate_' . $event_id),
            'account_payment_gateway' => $activeAccountPaymentGateway,
            'payment_gateway' => $paymentGateway
        ]);

        /*
         * If we're this far assume everything is OK and redirect them
         * to the the checkout page.
         */
        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'redirectUrl' => route('showEventCheckout', [
                        'event_id' => $event_id,
                        'is_embedded' => $this->is_embedded,
                    ]) . '#order_form',
            ]);
        }

        /*
         * Maybe display something prettier than this?
         */
        exit('Please enable Javascript in your browser.');
    }

    /**
     * Show the checkout page
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\View\View
     */
    public function showEventCheckout(Request $request, $event_id)
    {
        $order_session = session()->get('ticket_order_' . $event_id);

        if (!$order_session || $order_session['expires'] < Carbon::now()) {
            $route_name = $this->is_embedded ? 'showEmbeddedEventPage' : 'showEventPage';
            return redirect()->route($route_name, ['event_id' => $event_id]);
        }

        $secondsToExpire = Carbon::now()->diffInSeconds($order_session['expires']);

        $event = Event::findorFail($order_session['event_id']);

        $orderService = new OrderService($order_session['order_total'], $order_session['total_booking_fee'], $event);
        $orderService->calculateFinalCosts();

        $data = $order_session + [
                'event' => $event,
                'secondsToExpire' => $secondsToExpire,
                'is_embedded' => $this->is_embedded,
                'orderService' => $orderService
            ];

        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventPageCheckout', $data);
        }

        return view('Public.ViewEvent.EventPageCheckout', $data);
    }

    /**
     * Create the order, handle payment, update stats, fire off email jobs then redirect user
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function postCreateOrder(Request $request, $event_id)
    {

        /*
         * If there's no session kill the request and redirect back to the event homepage.
         */
        if (!session()->get('ticket_order_' . $event_id)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your session has expired.',
                'redirectUrl' => route('showEventPage', [
                    'event_id' => $event_id,
                ])
            ]);
        }

        $event = Event::findOrFail($event_id);
        $order = new Order;
        $ticket_order = session()->get('ticket_order_' . $event_id);

        $validation_rules = $ticket_order['validation_rules'];
        $validation_messages = $ticket_order['validation_messages'];

        $order->rules = $order->rules + $validation_rules;
        $order->messages = $order->messages + $validation_messages;

        if (!$order->validate($request->all())) {
            return response()->json([
                'status' => 'error',
                'messages' => $order->errors(),
            ]);
        }

        // Add the request data to a session in case payment is required off-site
        session()->push('ticket_order_' . $event_id . '.request_data', $request->except(['card-number', 'card-cvc']));

        if (!$ticket_order['order_requires_payment']) {
            // No payment required so go ahead and complete the order
            return $this->completeOrder($event_id);
        }

        // Check if the user has chosen to pay offline and if they are allowed
        if ($request->get('pay_offline') && $event->enable_offline_payments) {
            return $this->completeOrder($event_id);
        }

        $payment_service = new PaymentService($request, $event);
        $order_service = new OrderService($ticket_order['order_total'], $ticket_order['total_booking_fee'], $event);
        $order_service->calculateFinalCosts();

        try {
            $transaction_data = $payment_service->makeTransactionData($order_service->getGrandTotal(), $ticket_order);
            $gateway = $payment_service->getGateway($ticket_order);
            $transaction = $gateway->purchase($transaction_data);
            $response = $transaction->send();

            Log::info('Purchase request sent!');
            Log::debug($response->getData());

            // TODO: Save payment token?

            if ($response->isPending()) {

                return $this->handlePendingPayment($event, $response);
            } else if ($response->isSuccessful()) {

                return $this->handleSuccessPayment($event, $response);
            } elseif ($response->isRedirect()) {

                return $this->handleRedirectPayment($event, $response, $transaction_data, $ticket_order);
            } else {
                // display error to customer
                return response()->json([
                    'status' => 'error',
                    'message' => $response->getMessage(),
                ]);
            }
        } catch (\Exeption $e) {
            Log::error($e);
            $error = 'Sorry, there was an error processing your payment. Please try again.';
        }

        if ($error) {
            return response()->json([
                'status' => 'error',
                'message' => $error,
            ]);
        }
    }

    /**
     * Attempt to complete a user's payment when they return from
     * an off-site gateway
     *
     * @param Request $request
     * @param $event_id
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function showEventCheckoutPaymentReturn(Request $request, $event_id)
    {

        if ($request->get('is_payment_cancelled') == '1') {
            session()->flash('message', 'You cancelled your payment. You may try again.');
            return response()->redirectToRoute('showEventCheckout', [
                'event_id' => $event_id,
                'is_payment_cancelled' => 1,
            ]);
        }

        $ticket_order = session()->get('ticket_order_' . $event_id);
        $gateway = Omnipay::create($ticket_order['payment_gateway']->name);

        $gateway->initialize($ticket_order['account_payment_gateway']->config + [
                'testMode' => config('attendize.enable_test_payments'),
            ]);

        $transaction = $gateway->completePurchase($ticket_order['transaction_data'][0]);

        $response = $transaction->send();

        if ($response->isSuccessful()) {
            session()->push('ticket_order_' . $event_id . '.transaction_id', $response->getTransactionReference());
            return $this->completeOrder($event_id, false);
        } else {
            session()->flash('message', $response->getMessage());
            return response()->redirectToRoute('showEventCheckout', [
                'event_id' => $event_id,
                'is_payment_failed' => 1,
            ]);
        }
    }

    /**
     * Show the order details page
     *
     * @param Request $request
     * @param $order_reference
     * @return \Illuminate\View\View
     */
    public function showOrderDetails(Request $request, $order_reference)
    {
        $order = Order::where('order_reference', '=', $order_reference)->first();

        if (!$order) {
            abort(404);
        }

        $orderService = new OrderService($order->amount, $order->organiser_booking_fee, $order->event);
        $orderService->calculateFinalCosts();

        $data = [
            'order' => $order,
            'orderService' => $orderService,
            'event' => $order->event,
            'tickets' => $order->event->tickets,
            'is_embedded' => $this->is_embedded,
        ];

        if ($this->is_embedded) {
            return view('Public.ViewEvent.Embedded.EventPageViewOrder', $data);
        }

        return view('Public.ViewEvent.EventPageViewOrder', $data);
    }

    /**
     * Shows the tickets for an order - either HTML or PDF
     *
     * @param Request $request
     * @param $order_reference
     * @return \Illuminate\View\View
     */
    public function showOrderTickets(Request $request, $order_reference)
    {
        $order = Order::where('order_reference', '=', $order_reference)->first();

        if (!$order) {
            abort(404);
        }
        $images = [];
        $imgs = $order->event->images;
        foreach ($imgs as $img) {
            $images[] = base64_encode(file_get_contents(public_path($img->image_path)));
        }

        $data = [
            'order' => $order,
            'event' => $order->event,
            'tickets' => $order->event->tickets,
            'attendees' => $order->attendees,
            'css' => file_get_contents(public_path('assets/stylesheet/ticket.css')),
            'image' => base64_encode(file_get_contents(public_path($order->event->organiser->full_logo_path))),
            'images' => $images,
        ];

        if ($request->get('download') == '1') {
            return PDF::html('Public.ViewEvent.Partials.PDFTicket', $data, 'Tickets');
        }
        return view('Public.ViewEvent.Partials.PDFTicket', $data);
    }

    private function handlePendingPayment($event, $response)
    {
        session()->push('ticket_order_' . $event->id . '.transaction_id', $response->getTransactionReference());
        return $this->completeOrder($event->id, true);
    }

    private function handleSuccessPayment($event, $response)
    {
        session()->push('ticket_order_' . $event->id . '.transaction_id', $response->getTransactionReference());
        return $this->completeOrder($event->id);
    }

    private function handleRedirectPayment($event, $response, $transaction_data, $ticket_order)
    {
        // As we're going off-site for payment we need to store some data in a session so it's available when we return
        session()->push('ticket_order_' . $event->id . '.transaction_data', $transaction_data);
        Log::info("Redirect url: " . $response->getRedirectUrl());

        $return = [
            'status' => 'success',
            'redirectUrl' => $response->getRedirectUrl(),
            'message' => 'Redirecting to ' . $ticket_order['payment_gateway']->provider_name
        ];

        // GET method requests should not have redirectData on the JSON return string
        if ($response->getRedirectMethod() == 'POST') {
            $return['redirectData'] = $response->getRedirectData();
        }

        return response()->json($return);
    }

    /**
     * Complete an order
     *
     * @param $event_id
     * @param bool|false $isPendingPayment
     * @param bool|true $return_json
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    private function completeOrder($event_id, $isPendingPayment = false, $return_json = true)
    {
        DB::beginTransaction();
        try {
            $ticket_order = session()->get('ticket_order_' . $event_id);
            $request_data = $ticket_order['request_data'][0];
            $event = Event::findOrFail($ticket_order['event_id']);

            $orderService = new OrderService($ticket_order['order_total'], $ticket_order['total_booking_fee'], $event);

            // Create the order
            $order_status = config('attendize.order_complete');
            $order = $orderService->newOrder($ticket_order, $request_data, $order_status);
            if (isset($request_data['pay_offline']) || $isPendingPayment) {
                $order->order_status_id = config('attendize.order_awaiting_payment');
            }
            if (isset($request_data['pay_offline']) || $isPendingPayment) {
                $order->is_payment_received = 0;
            } else {
                $order->is_payment_received = 1;
            }
            $order->save();

            $orderService->updateSaleVolumes($order);
            $orderService->updateAffiliateStats($order, $ticket_order);
            $orderService->updateEventStats($order, $ticket_order);
            $orderService->addAttendees($order, $ticket_order, $request_data);

            //save the order to the database
            DB::commit();
        } catch (Exception $e) {

            Log::error($e);
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Whoops! There was a problem processing your order. Please try again.'
            ]);
        }

        //forget the order in the session
        session()->forget('ticket_order_' . $event->id);

        // Queue up some tasks - Emails to be sent, PDFs etc.
        Log::info('Firing the event');
        event(new OrderCompletedEvent($order));

        if ($return_json) {
            return response()->json([
                'status' => 'success',
                'redirectUrl' => route('showOrderDetails', [
                    'is_embedded' => $this->is_embedded,
                    'order_reference' => $order->order_reference,
                ]),
            ]);
        }

        return response()->redirectToRoute('showOrderDetails', [
            'is_embedded' => $this->is_embedded,
            'order_reference' => $order->order_reference,
        ]);
    }
}

