<?php

namespace App\Http\Controllers;

use App\Models\Lineitem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class OrderController extends Controller
{

    public function allOrders(){

        $total_orders=Order::count();
        $pushed_orders=Order::where('status',1)->count();
        $pending_orders=Order::where('tryvengo_status','Pending')->count();
        $delivered_orders=Order::where('tryvengo_status','Delivered')->count();

        $orders=Order::orderBy('order_number','desc')->paginate(30);
        return view('orders.index',compact('orders','total_orders','pushed_orders','pending_orders','delivered_orders'));
    }


    public function shopifyOrders($next = null){

        $shop=Auth::user();
        $orders = $shop->api()->rest('GET', '/admin/api/orders.json', [
            'limit' => 250,
            'page_info' => $next
        ]);
        if ($orders['errors'] == false) {
            if (count($orders['body']->container['orders']) > 0) {
                foreach ($orders['body']->container['orders'] as $order) {
                    $order = json_decode(json_encode($order));
                    $this->singleOrder($order,$shop);
                }
            }
            if (isset($orders['link']['next'])) {

                $this->shopifyOrders($orders['link']['next']);
            }
        }
        return Redirect::tokenRedirect('home', ['notice' => 'Orders Sync Successfully']);


    }
    public function singleOrder($order, $shop)
    {

        if($order->financial_status!='refunded' && $order->cancelled_at==null  ) {
            if ($order->cart_token) {
                $newOrder = Order::where('shopify_id', $order->id)->where('shop_id', $shop->id)->first();
                if ($newOrder == null) {
                    $newOrder = new Order();
                }
                $newOrder->shopify_id = $order->id;
                $newOrder->email = $order->email;
                $newOrder->order_number = $order->name;

                if (isset($order->shipping_address)) {
                    $newOrder->shipping_name = $order->shipping_address->name;
                    $newOrder->address1 = $order->shipping_address->address1;
                    $newOrder->address2 = $order->shipping_address->address2;
                    $newOrder->phone = $order->shipping_address->phone;
                    $newOrder->city = $order->shipping_address->city;
                    $newOrder->zip = $order->shipping_address->zip;
                    $newOrder->province = $order->shipping_address->province;
                    $newOrder->country = $order->shipping_address->country;
                }
                $newOrder->financial_status = $order->financial_status;
                $newOrder->fulfillment_status = $order->fulfillment_status;
                if (isset($order->customer)) {
                    $newOrder->first_name = $order->customer->first_name;
                    $newOrder->last_name = $order->customer->last_name;
                    $newOrder->customer_phone = $order->customer->phone;
                    $newOrder->customer_email = $order->customer->email;
                    $newOrder->customer_id = $order->customer->id;
                }
                $newOrder->shopify_created_at = date_create($order->created_at)->format('Y-m-d h:i:s');
                $newOrder->shopify_updated_at = date_create($order->updated_at)->format('Y-m-d h:i:s');
                $newOrder->tags = $order->tags;
                $newOrder->note = $order->note;
                $newOrder->total_price = $order->total_price;
                $newOrder->currency = $order->currency;

                $newOrder->subtotal_price = $order->subtotal_price;
                $newOrder->total_weight = $order->total_weight;
                $newOrder->taxes_included = $order->taxes_included;
                $newOrder->total_tax = $order->total_tax;
                $newOrder->currency = $order->currency;
                $newOrder->total_discounts = $order->total_discounts;
                $newOrder->shop_id = $shop->id;
                $newOrder->save();
                foreach ($order->line_items as $item) {
                    $new_line = Lineitem::where('shopify_id', $item->id)->where('order_id', $newOrder->id)->where('shop_id', $shop->id)->first();
                    if ($new_line == null) {
                        $new_line = new Lineitem();
                    }
                    $new_line->shopify_id = $item->id;
                    $new_line->shopify_product_id = $item->product_id;
                    $new_line->shopify_variant_id = $item->variant_id;
                    $new_line->title = $item->title;
                    $new_line->quantity = $item->quantity;
                    $new_line->sku = $item->sku;
                    $new_line->variant_title = $item->variant_title;
                    $new_line->title = $item->title;
                    $new_line->vendor = $item->vendor;
                    $new_line->price = $item->price;
                    $new_line->requires_shipping = $item->requires_shipping;
                    $new_line->taxable = $item->taxable;
                    $new_line->name = $item->name;
                    $new_line->properties = json_encode($item->properties, true);
                    $new_line->fulfillable_quantity = $item->fulfillable_quantity;
                    $new_line->fulfillment_status = $item->fulfillment_status;
                    $new_line->order_id = $newOrder->id;
                    $new_line->shop_id = $shop->id;
                    $new_line->shopify_order_id = $order->id;
                    $new_line->save();
                }
                $setting=Setting::first();
                $this->fulfillmentOrders($newOrder);
                if($setting->auto_push_orders==1){
                    $url = 'https://tryvengo.com/api/place-ecomerce-order';
                    $pickupEstimateTime = now()->addHours(4);
//            dd($pickupEstimateTime->format('d/m/Y h:i A'));

                    if($order->financial_status=='paid'){
                        $payment_mode=1;
                    }else{
                        $payment_mode=2;
                    }


                    if($setting->switch_account==0){
                        $email=$setting->email;
                        $password=$setting->password;
                        $pick_up_address_id=37;
                    }elseif ($setting->switch_account==1){
                        $email=$setting->email2;
                        $password=$setting->password2;
                        $pick_up_address_id=39;
                    }

                    $order_number='Shopify_'.$order->order_number;
                    $order_number = preg_replace("/[^a-zA-Z0-9]/", "", $order_number);

                    $data = [
                        'email' =>$email,
                        'password' =>$password,
                        'pick_up_address_id' =>$pick_up_address_id,
                        'item_type' => 'ds',
                        'invoice_id'=>$order_number,
                        'item_price' => $newOrder->total_price,
                        'payment_mode' => $payment_mode,
                        'pickup_estimate_time_type' => 0,
                        'pickup_estimate_time' => $pickupEstimateTime->format('d/m/Y h:i A'),
                        'recipient_name' => $newOrder->shipping_name,
                        'recipient_mobile' => $newOrder->phone,
                        'address_area' => $newOrder->city,
                        'address_block_number' => $newOrder->address2,
                        'address_street' => $newOrder->address1,
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

                    if ($responseData && $responseData['status']==1) {
                        $deliveryId = $responseData['delivery_id'];
                        $invoiceId = $responseData['invoice_id'];

                        $newOrder->delivery_id=$deliveryId;
                        $newOrder->invoice_id=$invoiceId;
                        $newOrder->status=1;
                        $newOrder->tryvengo_status='Pending';
                        $newOrder->save();

                    }


                }

            }


            elseif (stripos($order->tags,'releasit_cod_form')!== false) {

                $newOrder = Order::where('shopify_id', $order->id)->where('shop_id', $shop->id)->first();
                if ($newOrder == null) {
                    $newOrder = new Order();
                }
                $newOrder->shopify_id = $order->id;
                $newOrder->email = $order->email;
                $newOrder->order_number = $order->name;

                if (isset($order->shipping_address)) {
                    $newOrder->shipping_name = $order->shipping_address->name;
                    $newOrder->address1 = $order->shipping_address->address1;
                    $newOrder->address2 = $order->shipping_address->address2;
                    $newOrder->phone = $order->shipping_address->phone;
                    $newOrder->city = $order->shipping_address->city;
                    $newOrder->zip = $order->shipping_address->zip;
                    $newOrder->province = $order->shipping_address->province;
                    $newOrder->country = $order->shipping_address->country;
                }
                $newOrder->financial_status = $order->financial_status;
                $newOrder->fulfillment_status = $order->fulfillment_status;
                if (isset($order->customer)) {
                    $newOrder->first_name = $order->customer->first_name;
                    $newOrder->last_name = $order->customer->last_name;
                    $newOrder->customer_phone = $order->customer->phone;
                    $newOrder->customer_email = $order->customer->email;
                    $newOrder->customer_id = $order->customer->id;
                }
                $newOrder->shopify_created_at = date_create($order->created_at)->format('Y-m-d h:i:s');
                $newOrder->shopify_updated_at = date_create($order->updated_at)->format('Y-m-d h:i:s');
                $newOrder->tags = $order->tags;
                $newOrder->note = $order->note;
                $newOrder->total_price = $order->total_price;
                $newOrder->currency = $order->currency;

                $newOrder->subtotal_price = $order->subtotal_price;
                $newOrder->total_weight = $order->total_weight;
                $newOrder->taxes_included = $order->taxes_included;
                $newOrder->total_tax = $order->total_tax;
                $newOrder->currency = $order->currency;
                $newOrder->total_discounts = $order->total_discounts;
                $newOrder->shop_id = $shop->id;
                $newOrder->save();
                foreach ($order->line_items as $item) {
                    $new_line = Lineitem::where('shopify_id', $item->id)->where('order_id', $newOrder->id)->where('shop_id', $shop->id)->first();
                    if ($new_line == null) {
                        $new_line = new Lineitem();
                    }
                    $new_line->shopify_id = $item->id;
                    $new_line->shopify_product_id = $item->product_id;
                    $new_line->shopify_variant_id = $item->variant_id;
                    $new_line->title = $item->title;
                    $new_line->quantity = $item->quantity;
                    $new_line->sku = $item->sku;
                    $new_line->variant_title = $item->variant_title;
                    $new_line->title = $item->title;
                    $new_line->vendor = $item->vendor;
                    $new_line->price = $item->price;
                    $new_line->requires_shipping = $item->requires_shipping;
                    $new_line->taxable = $item->taxable;
                    $new_line->name = $item->name;
                    $new_line->properties = json_encode($item->properties, true);
                    $new_line->fulfillable_quantity = $item->fulfillable_quantity;
                    $new_line->fulfillment_status = $item->fulfillment_status;
                    $new_line->order_id = $newOrder->id;
                    $new_line->shop_id = $shop->id;
                    $new_line->shopify_order_id = $order->id;
                    $new_line->save();
                }

                $this->fulfillmentOrders($newOrder);
                $setting = Setting::first();
                if ($setting->auto_push_orders == 1) {
                    $url = 'https://tryvengo.com/api/place-ecomerce-order';
                    $pickupEstimateTime = now()->addHours(4);
//            dd($pickupEstimateTime->format('d/m/Y h:i A'));
                    $data = [
                        'email' => $setting->email,
                        'password' => $setting->password,
                        'pick_up_address_id' => 37,
                        'item_type' => 'ds',
                        'item_price' => $newOrder->total_price,
                        'payment_mode' => 2,
                        'pickup_estimate_time_type' => 0,
                        'pickup_estimate_time' => $pickupEstimateTime->format('d/m/Y h:i A'),
                        'recipient_name' => $newOrder->shipping_name,
                        'recipient_mobile' => $newOrder->phone,
                        'address_area' => $newOrder->city,
                        'address_block_number' => $newOrder->address2,
                        'address_street' => $newOrder->address1,
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

                    if ($responseData && $responseData['status']==1) {
                        $deliveryId = $responseData['delivery_id'];
                        $invoiceId = $responseData['invoice_id'];
                        $newOrder->delivery_id = $deliveryId;
                        $newOrder->invoice_id = $invoiceId;
                        $newOrder->status = 1;
                        $newOrder->tryvengo_status = 'Pending';
                        $newOrder->save();

                    }
                }
            }




        }
    }
    public function updateOrder($order, $shop)
    {

        if($order->financial_status!='refunded' && $order->cancelled_at==null  ) {
            if ($order->cart_token) {
                $newOrder = Order::where('shopify_id', $order->id)->where('shop_id', $shop->id)->first();
                if ($newOrder == null) {
                    $newOrder = new Order();
                }
                $newOrder->shopify_id = $order->id;
                $newOrder->email = $order->email;
                $newOrder->order_number = $order->name;

                if (isset($order->shipping_address)) {
                    $newOrder->shipping_name = $order->shipping_address->name;
                    $newOrder->address1 = $order->shipping_address->address1;
                    $newOrder->address2 = $order->shipping_address->address2;
                    $newOrder->phone = $order->shipping_address->phone;
                    $newOrder->city = $order->shipping_address->city;
                    $newOrder->zip = $order->shipping_address->zip;
                    $newOrder->province = $order->shipping_address->province;
                    $newOrder->country = $order->shipping_address->country;
                }
                $newOrder->financial_status = $order->financial_status;
                $newOrder->fulfillment_status = $order->fulfillment_status;
                if (isset($order->customer)) {
                    $newOrder->first_name = $order->customer->first_name;
                    $newOrder->last_name = $order->customer->last_name;
                    $newOrder->customer_phone = $order->customer->phone;
                    $newOrder->customer_email = $order->customer->email;
                    $newOrder->customer_id = $order->customer->id;
                }
                $newOrder->shopify_created_at = date_create($order->created_at)->format('Y-m-d h:i:s');
                $newOrder->shopify_updated_at = date_create($order->updated_at)->format('Y-m-d h:i:s');
                $newOrder->tags = $order->tags;
                $newOrder->note = $order->note;
                $newOrder->total_price = $order->total_price;
                $newOrder->currency = $order->currency;

                $newOrder->subtotal_price = $order->subtotal_price;
                $newOrder->total_weight = $order->total_weight;
                $newOrder->taxes_included = $order->taxes_included;
                $newOrder->total_tax = $order->total_tax;
                $newOrder->currency = $order->currency;
                $newOrder->total_discounts = $order->total_discounts;
                $newOrder->shop_id = $shop->id;
                $newOrder->save();
                foreach ($order->line_items as $item) {
                    $new_line = Lineitem::where('shopify_id', $item->id)->where('order_id', $newOrder->id)->where('shop_id', $shop->id)->first();
                    if ($new_line == null) {
                        $new_line = new Lineitem();
                    }
                    $new_line->shopify_id = $item->id;
                    $new_line->shopify_product_id = $item->product_id;
                    $new_line->shopify_variant_id = $item->variant_id;
                    $new_line->title = $item->title;
                    $new_line->quantity = $item->quantity;
                    $new_line->sku = $item->sku;
                    $new_line->variant_title = $item->variant_title;
                    $new_line->title = $item->title;
                    $new_line->vendor = $item->vendor;
                    $new_line->price = $item->price;
                    $new_line->requires_shipping = $item->requires_shipping;
                    $new_line->taxable = $item->taxable;
                    $new_line->name = $item->name;
                    $new_line->properties = json_encode($item->properties, true);
                    $new_line->fulfillable_quantity = $item->fulfillable_quantity;
                    $new_line->fulfillment_status = $item->fulfillment_status;
                    $new_line->order_id = $newOrder->id;
                    $new_line->shop_id = $shop->id;
                    $new_line->shopify_order_id = $order->id;
                    $new_line->save();
                }


            }

            $this->fulfillmentOrders($newOrder);
        }
    }


    public function SendOrderDelivery($id)
    {



        $shop = Auth::user();
        $order = Order::find($id);
        $setting=Setting::first();
        if($order){

try {
    $url = 'https://tryvengo.com/api/place-ecomerce-order';

    $pickupEstimateTime = now()->addHours(2);
//            dd($pickupEstimateTime->format('d/m/Y h:i A'));

    $phone= str_replace(' ', '', $order->phone);
    $phone = substr($phone, -8);

if($order->financial_status=='paid'){
    $payment_mode=1;
}else{
    $payment_mode=2;
}

if($setting->switch_account==0){
    $email=$setting->email;
    $password=$setting->password;
    $pick_up_address_id=37;
}elseif ($setting->switch_account==1){
    $email=$setting->email2;
    $password=$setting->password2;
    $pick_up_address_id=39;
}

$order_number='Shopify_'.$order->order_number;
    $order_number = preg_replace("/[^a-zA-Z0-9]/", "", $order_number);

    $data = [
        'email' => $email,
        'password' => $password,
        'pick_up_address_id' =>$pick_up_address_id,
        'item_type' => 'ds',
        'invoice_id'=>$order_number,
        'item_price' => $order->total_price,
        'payment_mode' => $payment_mode,
        'pickup_estimate_time_type' => 0,
        'pickup_estimate_time' => $pickupEstimateTime->format('d/m/Y h:i A'),
        'recipient_name' => $order->shipping_name,
        'recipient_mobile' =>$phone,
        'address_area' => $order->city,
        'address_block_number' => $order->city,
        'address_street' => $order->address1,
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

    if ($responseData['status'] == 1) {
        $deliveryId = $responseData['delivery_id'];
        $invoiceId = $responseData['invoice_id'];

        $order->delivery_id = $deliveryId;
        $order->invoice_id = $invoiceId;
        $order->status = 1;
        $order->tryvengo_status = 'Pending';
        $order->save();
        return Redirect::tokenRedirect('home', ['notice' => 'Order Pushed to Tryvengo Successfully']);
        // Now, $deliveryId and $invoiceId contain the respective values
    }else{
        return Redirect::tokenRedirect('home', ['error' => $responseData['message']]);
    }
}catch (\Exception $exception){

    dd($exception->getMessage());
}
        }

    }


    public function OrdersFilter(Request $request){


        $shop=Auth::user();
        $orders=Order::query();

        if($request->orders_filter!=null) {
            $orders = $orders->where('order_number', 'like', '%' . $request->orders_filter . '%')->orWhere('shipping_name', 'like', '%' . $request->orders_filter . '%');
        }

        if($request->tryvengo_status!=null) {
            $orders = $orders->where('tryvengo_status', $request->tryvengo_status );
        }

        if($request->order_status!=null) {
            $orders = $orders->where('status', $request->order_status );
        }

        if ($request->date_filter != null) {
            $orders = $orders->whereDate('created_at', $request->date_filter);
        }


        $order_ids=$orders->pluck('id')->toArray();



        $total_orders=Order::whereIn('id',$order_ids)->where('shop_id',$shop->id)->count();
        $pushed_orders=Order::whereIn('id',$order_ids)->where('status',1)->count();
        $pending_orders=Order::whereIn('id',$order_ids)->where('tryvengo_status','Pending')->count();
        $delivered_orders=Order::whereIn('id',$order_ids)->where('tryvengo_status','Delivered')->count();



        $orders=$orders->orderBy('id', 'DESC')->paginate(30);

        return view('orders.index',compact('orders','request','shop','total_orders','pushed_orders','pending_orders','delivered_orders'));
    }



    public function TrackOrder(){
        $setting=Setting::first();
        $url = 'https://tryvengo.com/api/track-order';
        $data = [
            'email' =>'orders@mubkhar.com',
            'password' => 'Mubkv9Qh@1',
           'invoice_id'=>'14874213'
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

        if($responseData['status']==1){

//           $order->tryvengo_status=$responseData['order_data']['order_status'];
//        $order->save();
        }

    }

    public function PushSelectedOrders(Request $request){
        if(isset($request->order_ids)) {
            $order_ids = explode(',', $request->order_ids);
            foreach ($order_ids as $order_id){

                $this->SendOrderDeliveryMultiple($order_id);
            }
            return Redirect::tokenRedirect('home', ['notice' => 'Order Pushed Successfuly']);
        }
    }

    public function SendOrderDeliveryMultiple($id)
    {
        $shop = Auth::user();
        $order = Order::find($id);
        $setting=Setting::first();
        if($order){

            try {
                $url = 'https://tryvengo.com/api/place-ecomerce-order';
                $pickupEstimateTime = now()->addHours(2);

                $phone= str_replace(' ', '', $order->phone);
                $phone = substr($phone, -8);

                if($order->financial_status=='paid'){
                    $payment_mode=1;
                }else{
                    $payment_mode=2;
                }
                if($setting->switch_account==0){
                    $email=$setting->email;
                    $password=$setting->password;
                    $pick_up_address_id=37;
                }elseif ($setting->switch_account==1){
                    $email=$setting->email2;
                    $password=$setting->password2;
                    $pick_up_address_id=39;
                }
                $order_number='Shopify_'.$order->order_number;
                $order_number = preg_replace("/[^a-zA-Z0-9]/", "", $order_number);
                $data = [
                    'email' => $email,
                    'password' => $password,
                    'pick_up_address_id' => $pick_up_address_id,
                    'item_type' => 'ds',
                    'invoice_id'=>$order_number,
                    'item_price' => $order->total_price,
                    'payment_mode' => $payment_mode,
                    'pickup_estimate_time_type' => 0,
                    'pickup_estimate_time' => $pickupEstimateTime->format('d/m/Y h:i A'),
                    'recipient_name' => $order->shipping_name,
                    'recipient_mobile' =>$phone,
                    'address_area' => $order->city,
                    'address_block_number' => $order->city,
                    'address_street' => $order->address1,
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

                if ($responseData['status'] == 1) {
                    $deliveryId = $responseData['delivery_id'];
                    $invoiceId = $responseData['invoice_id'];

                    $order->delivery_id = $deliveryId;
                    $order->invoice_id = $invoiceId;
                    $order->status = 1;
                    $order->tryvengo_status = 'Pending';
                    $order->save();

                }
            }catch (\Exception $exception){

                dd($exception->getMessage());
            }
        }

    }


    public function fulfillmentOrders($order){
        $shop = User::where('name', env('SHOP_NAME'))->first();
        $get_fulfillment_orders= $shop->api()->rest('get', '/admin/api/2023-01/orders/' . $order->shopify_id . '/fulfillment_orders.json');

        if ($get_fulfillment_orders['errors'] == false) {
            $get_fulfillment_orders = json_decode(json_encode($get_fulfillment_orders));
            foreach ($get_fulfillment_orders->body->fulfillment_orders as $fulfillment) {
                $order->shopify_fulfillment_order_id = $fulfillment->id;
                $order->save();

                foreach ($fulfillment->line_items as $line_item) {
                    $db_line_item = LineItem::where('shopify_id', $line_item->line_item_id)->first();
                    if (isset($db_line_item)) {
                        $db_line_item->shopify_fulfillment_order_id = $line_item->id;
                        $db_line_item->shopify_fulfillment_real_order_id = $line_item->fulfillment_order_id;
                        $db_line_item->assigned_location_id = $fulfillment->assigned_location_id;
                        $db_line_item->save();
                    }
                }
            }
        }
    }
}
