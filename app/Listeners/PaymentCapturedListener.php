<?php
/**
 * Created by PhpStorm.
 * User: huanvn
 * Date: 2018/09/16
 */

namespace App\Listeners;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateOrderPaymentChain;
use App\Jobs\UpdateCapturedPayment;
use App\Events\PaymentCapturedEvent;


class PaymentCapturedListener implements ShouldQueue
{
    use DispatchesJobs;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public function handle(PaymentCapturedEvent $event) {
        Log::info('Handle PaymentCapturedEvent: ' . $event->getTransactionId());

        //
        UpdateOrderPaymentChain::withChain(
            // 1. Update payment status
            new UpdateCapturedPayment($event)
            // 2. Send email (with attached ticket) to user
        )->dispatch();
    }
}
