<?php

namespace App\Jobs;

use App\Events\OrderCompletedEvent;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleCapturedPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($payload)
    {
        $this->payload = json_decode($payload, true);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Processing HandleCapturedPaymentWebhook: transaction_id=" . $this->getTransactionId());

        try {
            DB::beginTransaction();
            // Find order
            $order = Order::where('transaction_id', $this->getTransactionId())->firstOrFail();
            // TODO: Check detail payment info
            if ($order->order_status_id != config('attendize.order_complete')) {

                // 1. Update payment status
                $order->order_status_id = config('attendize.order_complete');
                $order->Save();

                // 2. Send email (with attached ticket) to user
                Log::info('Firing the event');
                event(new OrderCompletedEvent($order));
                DB::commit();
            }


        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error('Failed HandleCapturedPaymentWebhook > ' . $this->getTransactionId());
            Log::debug($ex);
        }

        Log::info("Done HandleCapturedPaymentWebhook: transaction_id=" . $this->getTransactionId());

    }

    public function getTransactionId()
    {
        return isset($this->payload['data']) ? $this->payload['data']['id'] : "";
    }
}
