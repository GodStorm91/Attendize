<?php
/**
 * Created by PhpStorm.
 * User: huanvn
 * Date: 2018/09/16
 */

namespace App\Jobs;

use Illuminate\Support\Facades\Log;

use App\Events\PaymentCapturedEvent;

class UpdateCapturedPayment extends Job implements ShouldQueue {

    protected $event;

    /**
     * UpdateCapturedPayment constructor.
     *
     * @param PaymentCapturedEvent $event
     */
    public function __construct(PaymentCapturedEvent $event) {

        $this->event = $event;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() {
        Log::info("Run UpdateCapturedPayment: transactionId=" . $this->event->getTransactionId());
    }
}
