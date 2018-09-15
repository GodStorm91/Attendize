<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\PaymentGateway;

class InsertKomojuPaymentGateway extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        PaymentGateway::where('name', 'komoju')->delete();
        $gateway = new PaymentGateway();
        $gateway->provider_name = 'komoju';
        $gateway->provider_url = 'https://komoju.com';
        $gateway->is_on_site = '0';
        $gateway->can_refund = '0';
        $gateway->name = 'Komoju';
        $gateway->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        PaymentGateway::where('name', 'komoju')->delete();
    }
}
