<?php

namespace App\Http\Controllers;

use App\Jobs\HandleCapturedPaymentWebhook;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Log;

class KomojuController extends Controller
{

    public function __construct(Request $request)
    {
        //
    }

    public function handleWebhook(Request $request)
    {
        try {
            $id = uniqid();
            Log::info("[Webhook][$id] ----------------------------------------------------------------------");
            Log::info("[Webhook][$id] Header: \n" . $request->headers);
            Log::info("[Webhook][$id] ----------------------------------------------------------------------");
            Log::info("[Webhook][$id] Payload: \n" . $request->getContent());
            // Capture input and handle and delay handler task
            HandleCapturedPaymentWebhook::dispatch($request->getContent());
        } catch (\Exception $ex) {
            Log::error($ex);
        }

        return "OK";
    }

    public function handleAuthorizedEvent(Request $request)
    {
        // Capture input and handle and delay handler task
        return "OK";
    }

}