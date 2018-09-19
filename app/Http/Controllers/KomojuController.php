<?php

namespace App\Http\Controllers;

use App\Jobs\HandleKomojuWebhook;
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
            $headers = $request->headers;
            $payload = $request->getContent();
            Log::info("[Webhook][$id] ----------------------------------------------------------------------");
            Log::info("[Webhook][$id] Header: \n" . $headers);
            Log::info("[Webhook][$id] ----------------------------------------------------------------------");
            Log::info("[Webhook][$id] Payload: \n" . $payload);

            //----------------------------------------------------------------------------------------------------------
            if ($this->verifySignature($request->header("X-Komoju-Signature"), $payload)) {
                // Capture input and handle and delay handler task
                HandleKomojuWebhook::dispatch($payload);
            } else {
                Log::error("[Webhook][$id] Bad request: X-Komoju-Signature");
                return response('bad_request', 400);
            }
            //----------------------------------------------------------------------------------------------------------

        } catch (\Exception $ex) {
            Log::error($ex);
        }

        return "OK";
    }

    private function verifySignature($signature, $payload)
    {
        $secret_token = config('komoju.webhook_secret_token');
        $hmac = hash_hmac('sha256', $payload, $secret_token);
        return $hmac === $signature;

    }

}