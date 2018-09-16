<?php
/**
 * Created by PhpStorm.
 * User: huanvn
 * Date: 2018/09/16
 */

namespace App\Events;

use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PaymentCapturedEvent extends Event
{

    use SerializesModels;

    public $payload;

    public $transactionId;

    public function __construct($payload)
    {
        Log::debug($payload);
        $this->payload = json_decode($payload, true);
    }

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array
     */
    public function broadcastOn()
    {
        return [];
    }

    public function getTransactionId()
    {
        return $this->payload->id;
    }

}