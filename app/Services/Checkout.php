<?php

namespace App\Services;

use Omnipay;

class Checkout {

    public $request;
    public $event;

    public function __construct($request, $event) {

        $this->request = $request;
        $this->event = $event;
    }

    public function makeTransactionData($amount, $ticket_order) {

        $gateway = $this->getPaymentGateway($ticket_order);

        $order_email = $this->request->get('order_email');
        $transaction_data = $transaction_data = [
            'receipt_email' => $order_email,
            'amount'      => $amount,
            'currency'    => $this->event->currency->code,
            'description' => 'Order for customer: ' . $order_email,
        ];

        if (config('attendize.enable_dummy_payment_gateway') == TRUE) {
            $formData = config('attendize.fake_card_data');
            $token = uniqid();
            $transaction_data += [
                'token'         => $token,
                'card' => $formData
            ];

            return $transaction_data;
        }

        $gateway_id = $ticket_order['payment_gateway']->id;
        //--------------------------------------------------------------------------------------------------------------
        // Dummy gatteway
        //--------------------------------------------------------------------------------------------------------------
        if ($gateway_id == config('attendize.payment_gateway_dummy')) {
            $formData = config('attendize.fake_card_data');
            $token = uniqid();
            $transaction_data += [
                'token'         => $token,
                'receipt_email' => $order_email,
                'card' => $formData
            ];
            return $transaction_data;
        }

        //--------------------------------------------------------------------------------------------------------------
        // Paypal gateway
        //--------------------------------------------------------------------------------------------------------------
        if ($gateway_id== config('attendize.payment_gateway_paypal')) {
            $transaction_data += [
                'cancelUrl' => route('showEventCheckoutPaymentReturn', [
                    'event_id'             => $this->event->id,
                    'is_payment_cancelled' => 1
                ]),
                'returnUrl' => route('showEventCheckoutPaymentReturn', [
                    'event_id'              => $this->event->id,
                    'is_payment_successful' => 1
                ]),
                'brandName' => isset($ticket_order['account_payment_gateway']->config['brandingName'])
                    ? $ticket_order['account_payment_gateway']->config['brandingName']
                    : $this->event->organiser->name
            ];
            return $transaction_data;
        }

        //--------------------------------------------------------------------------------------------------------------
        // Stripe gateway
        //--------------------------------------------------------------------------------------------------------------
        if ($gateway_id== config('attendize.payment_gateway_stripe')) {
            $token = $this->request->get('stripeToken');
            $transaction_data += [
                'token'         => $token,
                'receipt_email' => $this->request->get('order_email'),
            ];
        }

        //--------------------------------------------------------------------------------------------------------------
        // Komoju gateway
        //--------------------------------------------------------------------------------------------------------------
        if ($gateway_id== config('attendize.payment_gateway_komoju')) {
            $api_key = $ticket_order['account_payment_gateway']['config']['apiKey'];
            $token = $this->request->get('komojuToken');

            $transaction_data += [
                'token' => $token,
                'api_key' => $api_key,
                'tax' => '0',
            ];
            return $transaction_data;
        }

        //--------------------------------------------------------------------------------------------------------------
        // Default
        return $transaction_data;
    }


    public function getPaymentGateway($ticket_order) {
        if (config('attendize.enable_dummy_payment_gateway') == TRUE) {
            $gateway = Omnipay::create('Dummy');
            $gateway->initialize();
        } else {
            $gateway = Omnipay::create($ticket_order['payment_gateway']->name);
            $gateway->initialize($ticket_order['account_payment_gateway']->config + [
                    'testMode' => config('attendize.enable_test_payments'),
                ]);
        }

        return $gateway;
    }


}