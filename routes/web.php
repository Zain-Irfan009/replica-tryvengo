<?php

use App\Models\Lineitem;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/trackorder', [App\Http\Controllers\OrderController::class, 'TrackOrder']);

Route::group(['middleware' => ['verify.shopify']], function () {
    Route::get('/', [App\Http\Controllers\OrderController::class, 'allOrders'])->name('home');
    Route::get('sync-order', [App\Http\Controllers\OrderController::class, 'shopifyOrders'])->name('sync.orders');
    Route::get('send-order-delivery/{id}', [App\Http\Controllers\OrderController::class, 'SendOrderDelivery'])->name('send.order.delivery');
    Route::get('orders-filter', [App\Http\Controllers\OrderController::class, 'OrdersFilter'])->name('orders.filter');
    Route::post('push-selected-orders', [App\Http\Controllers\OrderController::class, 'PushSelectedOrders'])->name('push.selected.orders');


    Route::get('settings', [App\Http\Controllers\SettingController::class, 'Settings'])->name('settings');
    Route::post('save-settings', [App\Http\Controllers\SettingController::class, 'SettingsSave'])->name('settings.save');

});


Route::get('/testing1', function() {

    $shop=\App\Models\User::where('name','mubkhar-fragrances.myshopify.com')->first();
$order=\App\Models\Order::where('id','7173')->first();

//                        $scheduled_fulfillment = [
//                        "fulfillment_order" => [
//
//                                // Params
//
//                                // Body
//
//                                        "new_fulfill_at" => "2024-11-19 19:59 UTC",
//                                            "status"=>'scheduled'
//
//
//
//                        ]
//                    ];
//
//
////dd($scheduled_fulfillment);
//
//                    $res = $shop->api()->rest('POST', '/admin/fulfillment_orders/'.$order->shopify_fulfillment_order_id.'/reschedule.json',$scheduled_fulfillment);
//                    dd($res);


    $OrderItems = Lineitem::where('order_id',$order->id)->get();
    $uniqueRealOrderIds = $OrderItems->groupBy('shopify_fulfillment_real_order_id');
    foreach ($uniqueRealOrderIds as $orderId => $orderItems) {

        $handle_fulfillment = [
            "fulfillment" => [

                "status"=>"scheduled",

                "line_items_by_fulfillment_order" => [


                ]
            ]
        ];

        $line_items = LineItem::where('order_id', $order->id)->where('shopify_fulfillment_real_order_id', $orderId)->get();
        $handle_temp_line_items=array();

        foreach ($line_items as $item) {

            array_push($handle_temp_line_items,[

                "id"=>$item->shopify_fulfillment_order_id,
                "quantity"=>$item->quantity,
            ]);

        }
        array_push($handle_fulfillment["fulfillment"]["line_items_by_fulfillment_order"],[
            "fulfillment_order_id"=>$orderId,
            "fulfillment_order_line_items"=>$handle_temp_line_items,

        ]);
        $res = $shop->api()->rest('POST', '/admin/fulfillments.json',$handle_fulfillment);
dd($res);

    }



    $shop = \Illuminate\Support\Facades\Auth::user();
    $shop=\App\Models\User::where('name','mubkhar-fragrances.myshopify.com')->first();
//$shop=\App\Models\User::where('name','prod-awake-water.myshopify.com')->first();
    $response = $shop->api()->rest('GET', '/admin/webhooks.json');

//    $response = $shop->api()->rest('delete', '/admin/api/webhooks/1418449715478.json');
    dd($response);
    $orders = $shop->api()->rest('POST', '/admin/webhooks.json', [

        "webhook" => array(
            "topic" => "orders/create",
            "format" => "json",
            "address" => env('APP_URL')."/webhook/order-create"
        )
    ]);
    dd($orders);
    dd($response);
})->name('getwebbhook');
