<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Events\PaymentCapturedEvent;

class WebhookController extends Controller
{

    public function __construct(Request $request)
    {
        //
    }

    public function handleCapturedEvent(Request $request)
    {
        // Capture input and handle and delay handler task
        event(new PaymentCapturedEvent($request->getContent()));
        return "OK";
    }

    public function handleAuthorizedEvent(Request $request)
    {
        // Capture input and handle and delay handler task
        // event(new PaymentCapturedEvent($request->getContent()));
        return "OK";
    }

}