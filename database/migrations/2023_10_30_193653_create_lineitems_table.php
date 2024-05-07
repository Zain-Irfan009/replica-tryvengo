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
        Schema::create('lineitems', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('shopify_id')->unsigned()->nullable();
            $table->bigInteger('shopify_order_id')->unsigned()->nullable();
            $table->bigInteger('shopify_product_id')->unsigned()->nullable();
            $table->bigInteger('shopify_variant_id')->unsigned()->nullable();
            $table->longText('title')->nullable();
            $table->bigInteger('quantity')->nullable();
            $table->text('sku')->nullable();
            $table->longText('variant_title')->nullable();
            $table->longText('vendor')->nullable();
            $table->double('price')->nullable();
            $table->text('requires_shipping')->nullable();
            $table->string('taxable')->nullable();
            $table->longText('name')->nullable();
            $table->longText('properties')->nullable();
            $table->bigInteger('fulfillable_quantity')->nullable();
            $table->longText('fulfillment_status')->nullable();
            $table->bigInteger('order_id')->unsigned()->nullable();
            $table->bigInteger('shop_id')->unsigned()->nullable();
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
        Schema::dropIfExists('lineitems');
    }
};
