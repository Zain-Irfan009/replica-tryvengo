<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('dhub_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('OrderId')->nullable();
            $table->bigInteger('StatusId')->nullable();
            $table->bigInteger('OrderType')->nullable();
            $table->bigInteger('SubOrderStatusId')->nullable();
            $table->longText('DriverEmail')->nullable();
            $table->longText('DriverId')->nullable();
            $table->longText('DriverName')->nullable();
            $table->longText('DriverPhone')->nullable();
            $table->longText('DriverLatitude')->nullable();
            $table->longText('DriverLongitude')->nullable();
            $table->longText('TrackingLink')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('dhub_orders');
    }
};
