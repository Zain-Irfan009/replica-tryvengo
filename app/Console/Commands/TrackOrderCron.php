<?php

namespace App\Console\Commands;

use App\Models\Lineitem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;

class TrackOrderCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trackorder:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $shop = User::where('name', env('SHOP_NAME'))->first();
        $setting=Setting::first();
        $orders=Order::where('status',1)->where('tryvengo_status','!=','Delivered')->orderBy('id','desc')->get();
//        $orders=Order::where('id',8851)->get();

        $url = 'https://tryvengo.com/api/track-order';
        foreach ($orders as $order){

            $email=$setting->email;
            $password=$setting->password;

//            if($setting->switch_account==0){
//                $email=$setting->email;
//                $password=$setting->password;
//
//            }elseif ($setting->switch_account==1){
//                $email=$setting->email2;
//                $password=$setting->password2;
//
//            }

            $data = [
                'email' =>$email,
                'password' => $password,
                'invoice_id'=>$order->invoice_id,
            ];

            // Build the cURL request
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false),
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($data), // Convert data to a query string
                CURLOPT_HTTPHEADER => array(
                    'email: ' . $setting->email,
                    'password: ' . $setting->password,
                    'Content-Type: application/x-www-form-urlencoded',
                ),
            ));

            // Execute the cURL request
            $response = curl_exec($curl);


            // Close the cURL session
            curl_close($curl);

            // Decode the JSON response
            $responseData = json_decode($response, true);

            if($responseData && $responseData['status']==1) {

                $order->tryvengo_status = $responseData['order_data']['order_status'];
                $order->save();

                $check_order = $shop->api()->rest('get', '/admin/orders/' . $order->shopify_id . '.json');
                if ($check_order['errors'] == false){
                    $check_order = json_decode(json_encode($check_order['body']['container']['order']));

                $tags = $check_order->tags;
                $tags_to_remove = array('Pending', 'Confirm', 'Pick up in Progress', 'Reached Pickup Location', 'Picked', 'Out For Delivery', 'Reached Delivery Location', 'Delivered', 'Cancel', 'Rescheduled', 'Reject', 'Return');
                $tags = str_replace($tags_to_remove, '', $tags);
                $tags = trim($tags);
                $get = $shop->api()->rest('put', '/admin/orders/' . $order->shopify_id . 'json', [
                    "order" => [
                        "tags" => $tags . ',' . $order->tryvengo_status,
                    ]
                ]);

                if ($order->tryvengo_status == 'Picked') {

//                    $scheduled_fulfillment = [
//                        "fulfillment_order" => [
//
//                                // Params
//
//                                // Body
//                                [
//                                        "new_fulfill_at" => "2023-12-3 19:59 UTC"
//
//                                ]
//
//                        ]
//                    ];
//
//
//dd($scheduled_fulfillment);
//
//                    $res = $shop->api()->rest('POST', '/admin/fulfillment_orders/'.$order->shopify_fulfillment_order_id.'/reschedule.json',$scheduled_fulfillment);
//                    dd($res);

                } elseif ($order->tryvengo_status == 'Delivered') {

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
                } elseif ($order->tryvengo_status == 'Cancel' || $order->tryvengo_status == 'Reject' || $order->tryvengo_status == 'Return') {
                    $cancel = $shop->api()->rest('post', '/admin/orders/' . $order->shopify_id . '/cancel.json', [
                        'order' => [
                        ]
                    ]);

                }
            }
            }
        }
    }
}
