<?php

namespace App\Http\Controllers;

use App\Models\DhubOrder;
use App\Models\Lineitem;
use App\Models\Log;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function handleDHubOrderStatus(Request $request)
    {
        // Log the request payload for debugging

try {
    // Find the order in your system using the OrderId from the webhook
    $order = Order::where('dhub_id', $request->input('OrderId'))->first();
    $shop = User::where('name', env('SHOP_NAME'))->first();
    if ($order) {

        $order->dhub_status_id=$request->input('StatusId');
        $order->save();

        if($order->dhub_status_id==11){
            $status='Unassigned';
        }elseif ($order->dhub_status_id==1){
            $status='Assigned';
        }
        elseif ($order->dhub_status_id==2){
            $status='Inprogress';
        }
        elseif ($order->dhub_status_id==3){
            $status='Successful';
        }
        elseif ($order->dhub_status_id==4){
            $status='Failed';
        }
        elseif ($order->dhub_status_id==9){
            $status='Canceled';
        }
        elseif ($order->dhub_status_id==5){
            $status='Accepted';
        }
        elseif ($order->dhub_status_id==10){
            $status='Declined';
        }


        $order->tryvengo_status=$status;
        $order->save();
        $check_order = $shop->api()->rest('get', '/admin/orders/' . $order->shopify_id . '.json');
        if ($check_order['errors'] == false) {
            $check_order = json_decode(json_encode($check_order['body']['container']['order']));

            $tags = $check_order->tags;
            $tags_to_remove = array('Unassigned', 'Assigned', 'Inprogress', 'Successful', 'Failed', 'Canceled', 'Accepted', 'Declined');
            $tags = str_replace($tags_to_remove, '', $tags);
            $tags = trim($tags);
            $get = $shop->api()->rest('put', '/admin/orders/' . $order->shopify_id . 'json', [
                "order" => [
                    "tags" => $tags . ',' . $status,
                ]
            ]);
        }

        if($status=='Successful') {
            $OrderItems = Lineitem::where('order_id', $order->id)->get();
            $uniqueRealOrderIds = $OrderItems->groupBy('shopify_fulfillment_real_order_id');
            foreach ($uniqueRealOrderIds as $orderId => $orderItems) {

                $handle_fulfillment = [
                    "fulfillment" => [

                        "line_items_by_fulfillment_order" => [


                        ]
                    ]
                ];

                $line_items = LineItem::where('order_id', $order->id)->where('shopify_fulfillment_real_order_id', $orderId)->get();
                $handle_temp_line_items = array();

                foreach ($line_items as $item) {

                    array_push($handle_temp_line_items, [

                        "id" => $item->shopify_fulfillment_order_id,
                        "quantity" => $item->quantity,
                    ]);

                }
                array_push($handle_fulfillment["fulfillment"]["line_items_by_fulfillment_order"], [
                    "fulfillment_order_id" => $orderId,
                    "fulfillment_order_line_items" => $handle_temp_line_items,

                ]);
                $res = $shop->api()->rest('POST', '/admin/fulfillments.json', $handle_fulfillment);


            }

            $query = 'mutation orderMarkAsPaid($input: OrderMarkAsPaidInput!) {
                                          orderMarkAsPaid(input: $input) {
                                            order {
                                             id
                                            }
                                            userErrors {
                                              field
                                              message
                                            }
                                          }
                                        }
                                        ';

            $orderBeginVariables = [
                "input" => [
                    'id' => 'gid://shopify/Order/' . $order->shopify_id
                ]
            ];
            $orderEditBegin = $shop->api()->graph($query, $orderBeginVariables);

            if (!$orderEditBegin['errors']) {

            }
        }elseif ($status=='Canceled' || $status=='Failed' || $status=='Declined'){
            $cancel = $shop->api()->rest('post', '/admin/orders/' . $order->shopify_id . '/cancel.json', [
                'order' => [
                ]
            ]);
        }
    }
    // Update order status and other details
//    $order->OrderId = $request->input('OrderId');
//    $order->StatusId = $request->input('StatusId');
//    $order->OrderType = $request->input('OrderType');
//    $order->SubOrderStatusId = $request->input('SubOrderStatusId');
//    $order->DriverEmail = $request->input('Data.DriverEmail');
//    $order->DriverId = $request->input('Data.DriverId');
//    $order->DriverName = $request->input('Data.DriverName');
//    $order->DriverPhone = $request->input('Data.DriverPhone');
//    $order->DriverLatitude = $request->input('Data.DriverLatitude');
//    $order->DriverLongitude = $request->input('Data.DriverLongitude');
//    $order->TrackingLink = $request->input('Data.TrackingLink');

//    $order->save();

    // Respond with success status
    return response()->json(['status' => 'success', 'message' => 'Order status updated'], 200);
}catch (\Exception $exception){

    $log=new Log();
    $log->type='webhook';
    $log->error=json_encode($exception->getMessage());
    $log->save();
        // If order is not found, respond with an error
        return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
    }
    }
}
