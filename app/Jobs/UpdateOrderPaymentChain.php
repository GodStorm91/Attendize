<?php
/**
 * Created by PhpStorm.
 * User: huanvn
 * Date: 2018/09/16
 */

namespace App\Jobs;

use Illuminate\Support\Facades\Log;

class UpdateOrderPaymentChain extends Job implements ShouldQueue
{

    use InteractsWithQueue, SerializesModels, DispatchesJobs;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("Start: UpdateOrderPaymentChain");
    }

}
