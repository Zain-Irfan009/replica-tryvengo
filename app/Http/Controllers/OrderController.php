<?php

namespace App\Http\Controllers;

use App\Models\Lineitem;
use App\Models\Log;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class OrderController extends Controller
{

    public function allOrders(){

        $total_orders=Order::count();
        $pushed_orders=Order::where('status',1)->count();
        $pending_orders=Order::where('tryvengo_status','Unassigned')->count();
        $delivered_orders=Order::where('tryvengo_status','Successful')->count();

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
                    $newOrder->province_code = $order->shipping_address->province_code;
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

                if (is_array($order->note_attributes) && count($order->note_attributes) > 0) {
                    // Initialize an array to hold the filtered attributes
                    $filteredAttributes = [];
                    $filtered_block_no_Attributes = [];
                    // Loop through the note_attributes to find the relevant one
                    foreach ($order->note_attributes as $attribute) {
                        if (isset($attribute->name) && $attribute->name === 'Sub City') {
                            // Add only the Sub City attribute to the filtered array
                            $filteredAttributes[] = $attribute;

                        }
                        if (isset($attribute->name) && $attribute->name === 'block_no') {

                            $filtered_block_no_Attributes[] = $attribute;

                        }

                    }

                    // If we found the Sub City attribute, save it to the new order
                    if (count($filteredAttributes) > 0) {
                        $newOrder->note_attributes = json_encode($filteredAttributes); // Store as JSON
                    }
                    if (count($filtered_block_no_Attributes) > 0) {
                        $newOrder->note_attributes_block_no = json_encode($filtered_block_no_Attributes); // Store as JSON
                    }
                }

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
//                if($setting->auto_push_orders==1){
//                    $url = 'https://tryvengo.com/api/place-ecomerce-order';
//                    $pickupEstimateTime = now()->addHours(4);
////            dd($pickupEstimateTime->format('d/m/Y h:i A'));
//
//                    if($order->financial_status=='paid'){
//                        $payment_mode=1;
//                    }else{
//                        $payment_mode=2;
//                    }
//
//
//                    if($setting->switch_account==0){
//                        $email=$setting->email;
//                        $password=$setting->password;
//                        $pick_up_address_id=37;
//                    }elseif ($setting->switch_account==1){
//                        $email=$setting->email2;
//                        $password=$setting->password2;
//                        $pick_up_address_id=39;
//                    }
//
//                    $order_number='Shopify_'.$order->order_number;
//                    $order_number = preg_replace("/[^a-zA-Z0-9]/", "", $order_number);
//
//                    //script code
//                    //area code
////                    $cityNames = [
////                        'Abbasiya', 'Abdali', 'Abdullah Al Mubarak', 'Abdullah Al Salem', 'Abu Al Hasaniya',
////                        'Abu Fatira', 'Abu Halifa', 'Adailiya', 'Ahmadi', 'Airport - Al Dajeej',
////                        'Airport - Subhan', 'Al Adan', 'Al Bidaa', 'Al Dajeej', 'Al Dubaiya Chalets',
////                        'Al Farwaniyah', 'Al Jahra', 'Al Julaiaa', 'Al Khuwaisat', 'Al Magwa',
////                        'Al Masayel', 'Al Matla', 'Al Nuwaiseeb', 'Al Qurain', 'Al Qusour',
////                        'Al Sabriya', 'Al Shadadiya', 'Al Siddeeq', 'Al Wafrah', 'Al Zour',
////                        'Ali Sabah Al Salem', 'Amghara', 'Andalous', 'Anjafa', 'Ardiya',
////                        'Bayan', 'Bnaider', 'Bnied Al Gar', 'Bubiyan Island', 'Daher',
////                        'Daiya', 'Dasma', 'Dasman', 'Doha', 'Doha Port',
////                        'East Al Ahmadi', 'Eqaila', 'Fahad Al Ahmad', 'Fahaheel', 'Faiha',
////                        'Fintas', 'Firdous', 'Funaitees', 'Ghornata', 'Hadiya',
////                        'Hateen', 'Hawally', 'Herafi Ardiya', 'Ishbiliya', 'Jaber Al Ahmad',
////                        'Jaber Al Ali', 'Jabriya', 'Jleeb Al Shuyoukh', 'Kabd', 'Kaifan',
////                        'Khairan Chalets & Residental', 'Khaitan', 'Khaldiya', 'Kuwait City', 'Mahboula',
////                        'Maidan Hawally', 'Mangaf', 'Mansouriya', 'Messila', 'Mina Abd Allah',
////                        'Mina Abd Allah Chalets', 'Mina Ahmadi', 'Mirqab', 'Mishref', 'Mubarak Al Abdullah',
////                        'Mubarak Al Kabeer', 'Mubarakia Camps', 'Naeem', 'Nahda', 'Naseem',
////                        'New Khairan City', 'New Wafra', 'North West Al Sulaibikhat', 'Nuzha', 'Omariya',
////                        'Oyoun', 'Qadsiya', 'Qairawan', 'Qasr', 'Qibla',
////                        'Qortuba', 'Rabiya', 'Rai', 'Rai Industrial', 'Rawda',
////                        'Rehab', 'Riggai', 'Riqqa', 'Rumaithiya', 'Saad Al Abdullah',
////                        'Sabah Al Ahmad', 'Sabah Al Ahmad Chalets & Residental', 'Sabah Al Nasser', 'Sabah Al Salem', 'Salam',
////                        'Salhiya', 'Salmi', 'Salmiya', 'Salwa', 'Sawabir',
////                        'Shaab', 'Shamiya', 'Sharq', 'Shuaiba', 'Shuhada',
////                        'Shuwaikh', 'Shuwaikh Free Trade Zone', 'Shuwaikh Industrial', 'Shuwaikh Port', 'Silk City',
////                        'South Abdullah Al Mubarak', 'South Subahiya', 'South Wista', 'Subahiya', 'Subhan',
////                        'Subiya', 'Sulaibikhat', 'Sulaibiya', 'Sulaibiya Industrial', 'Surra',
////                        'Taima', 'Waha', 'West Abdullah Al Mubarak', 'West Abu Fatira', 'Wista',
////                        'Yarmouk', 'Zahra',
////                    ];
////
////                    // Arabic to English city names mapping
////                    $arabicToEnglish = [
////                        "العباسيّة" => "Abbasiya",
////                        "العبدلي" => "Abdali",
////                        "عبدالله المبارك" => "Abdullah Al Mubarak",
////                        "عبدالله السالم" => "Abdullah Al Salem",
////                        "ابو الحصانية" => "Abu Al Hasaniya",
////                        "ابو فطيرة" => "Abu Fatira",
////                        "ابو حليفة" => "Abu Halifa",
////                        "العديلية" => "Adailiya",
////                        "الاحمدي" => "Ahmadi",
////                        "المطار - الضجيج" => "Airport - Al Dajeej",
////                        "المطار - صبحان" => "Airport - Subhan",
////                        "العدان" => "Al Adan",
////                        "البدع" => "Al Bidaa",
////                        "الضجيج" => "Al Dajeej",
////                        "شاليهات الضباعية" => "Al Dubaiya Chalets",
////                        "الفروانية" => "Al Farwaniyah",
////                        "الجهراء" => "Al Jahra",
////                        "الجليعة" => "Al Julaiaa",
////                        "الخويسات" => "Al Khuwaisat",
////                        "المقوع" => "Al Magwa",
////                        "المسايل" => "Al Masayel",
////                        "المطلاع" => "Al Matla",
////                        "النويصيب" => "Al Nuwaiseeb",
////                        "القرين" => "Al Qurain",
////                        "القصور" => "Al Qusour",
////                        "الصابرية" => "Al Sabriya",
////                        "الشدادية" => "Al Shadadiya",
////                        "الصديق" => "Al Siddeeq",
////                        "الوفرة" => "Al Wafrah",
////                        "الزور" => "Al Zour",
////                        "علي صباح السالم" => "Ali Sabah Al Salem",
////                        "امغره" => "Amghara",
////                        "الاندلس" => "Andalous",
////                        "أنجفة" => "Anjafa",
////                        "العارضية" => "Ardiya",
////                        "بيان" => "Bayan",
////                        "بنيدر" => "Bnaider",
////                        "بنيد القار" => "Bnied Al Gar",
////                        "جزيرة بوبيان" => "Bubiyan Island",
////                        "الظهر" => "Daher",
////                        "الدعية" => "Daiya",
////                        "الدسمة" => "Dasma",
////                        "دسمان" => "Dasman",
////                        "الدوحة" => "Doha",
////                        "ميناء الدوحة" => "Doha Port",
////                        "شرق الأحمدي" => "East Al Ahmadi",
////                        "العقيلة" => "Eqaila",
////                        "فهد الأحمد" => "Fahad Al Ahmad",
////                        "الفحيحيل" => "Fahaheel",
////                        "الفيحاء" => "Faiha",
////                        "الفنطاس" => "Fintas",
////                        "الفردوس" => "Firdous",
////                        "فنيطيس" => "Funaitees",
////                        "غرناطة" => "Ghornata",
////                        "هدية" => "Hadiya",
////                        "حطين" => "Hateen",
////                        "حولي" => "Hawally",
////                        "العارضية الحرفية" => "Herafi Ardiya",
////                        "إشبيلية" => "Ishbiliya",
////                        "جابر الاحمد" => "Jaber Al Ahmad",
////                        "جابر العلي" => "Jaber Al Ali",
////                        "الجابرية" => "Jabriya",
////                        "جليب الشيوخ" => "Jleeb Al Shuyoukh",
////                        "كبد" => "Kabd",
////                        "كيفان" => "Kaifan",
////                        "شاليهات الخيران والسكنية" => "Khairan Chalets & Residental",
////                        "خيطان" => "Khaitan",
////                        "الخالدية" => "Khaldiya",
////                        "مدينة الكويت" => "Kuwait City",
////                        "المهبولة" => "Mahboula",
////                        "ميدان حولي" => "Maidan Hawally",
////                        "المنقف" => "Mangaf",
////                        "المنصورية" => "Mansouriya",
////                        "المسيله" => "Messila",
////                        "ميناء عبد الله" => "Mina Abd Allah",
////                        "شاليهات ميناء عبد الله" => "Mina Abd Allah Chalets",
////                        "ميناء الأحمدي" => "Mina Ahmadi",
////                        "المرقاب" => "Mirqab",
////                        "مشرف" => "Mishref",
////                        "مبارك العبدالله" => "Mubarak Al Abdullah",
////                        "مبارك الكبير" => "Mubarak Al Kabeer",
////                        "معسكرات المباركية" => "Mubarakia Camps",
////                        "النعيم" => "Naeem",
////                        "النهضة" => "Nahda",
////                        "النسيم" => "Naseem",
////                        "مدينة الخيران الجديدة" => "New Khairan City",
////                        "الوفرة الجديدة" => "New Wafra",
////                        "شمال غرب الصليبيخات" => "North West Al Sulaibikhat",
////                        "النزهة" => "Nuzha",
////                        "العمرية" => "Omariya",
////                        "العيون" => "Oyoun",
////                        "القادسية" => "Qadsiya",
////                        "القيروان" => "Qairawan",
////                        "القصر" => "Qasr",
////                        "قبلة" => "Qibla",
////                        "قرطبة" => "Qortuba",
////                        "الرابية" => "Rabiya",
////                        "الري" => "Rai",
////                        "الري الصناعية" => "Rai Industrial",
////                        "الروضة" => "Rawda",
////                        "الرحاب" => "Rehab",
////                        "الرقعي" => "Riggai",
////                        "الرقة" => "Riqqa",
////                        "الرميثية" => "Rumaithiya",
////                        "سعد العبدالله" => "Saad Al Abdullah",
////                        "صباح الأحمد" => "Sabah Al Ahmad",
////                        "شاليهات صباح الأحمد والسكنية" => "Sabah Al Ahmad Chalets & Residental",
////                        "صباح الناصر" => "Sabah Al Nasser",
////                        "صباح السالم" => "Sabah Al Salem",
////                        "السلام" => "Salam",
////                        "الصالحية" => "Salhiya",
////                        "السالمي" => "Salmi",
////                        "السالمية" => "Salmiya",
////                        "سلوى" => "Salwa",
////                        "الصوابر" => "Sawabir",
////                        "الشعب" => "Shaab",
////                        "الشامية" => "Shamiya",
////                        "شرق" => "Sharq",
////                        "الشعيبة" => "Shuaiba",
////                        "الشهداء" => "Shuhada",
////                        "الشويخ" => "Shuwaikh",
////                        "المنطقة الحرة" => "Shuwaikh Free Trade Zone",
////                        "الشويخ الصناعية" => "Shuwaikh Industrial",
////                        "ميناء الشويخ" => "Shuwaikh Port",
////                        "مدينة الحرير" => "Silk City",
////                        "جنوب عبدالله المبارك" => "South Abdullah Al Mubarak",
////                        "جنوب الصباحية" => "South Subahiya",
////                        "جنوب وسطي" => "South Wista",
////                        "الصباحية" => "Subahiya",
////                        "صبحان" => "Subhan",
////                        "الصبيه" => "Subiya",
////                        "الصليبيخات" => "Sulaibikhat",
////                        "الصليبية" => "Sulaibiya",
////                        "الصليبية الصناعية" => "Sulaibiya Industrial",
////                        "السرة" => "Surra",
////                        "تيماء" => "Taima",
////                        "الواحة" => "Waha",
////                        "غرب عبدالله المبارك" => "West Abdullah Al Mubarak",
////                        "غرب ابو فطيرة" => "West Abu Fatira",
////                        "وسطي" => "Wista",
////                        "اليرموك" => "Yarmouk",
////                        "الزهراء" => "Zahra",
////                    ];
////
////
////                    // Sample input from request
////                    $inputCity =$newOrder->city;
////
////                    // Translate Arabic input to English
////                    $inputCityTranslated = $arabicToEnglish[$inputCity] ?? $inputCity;
////
////                    // Find the best match for the input city
////                    $bestMatch = $this->findBestMatch($inputCityTranslated, $cityNames);
////
////                    if($bestMatch){
////                        $city=$bestMatch;
////                    }else{
////                        $city=$newOrder->city;
////                    }
//
//
//                    $cityMap = [
//                        'Al Ahmadi' => 'Ahmadi',
//                        'Al Farwaniyah' => 'Al Farwaniyah',
//                        'Hawalli' => 'Hawally',
//                        'Mubarak Al-Kabeer' => 'Mubarak Al Kabeer',
//                        'Al Jahra' => 'Al Jahra',
//                        'Al Asimah' => 'Kuwait City',
//                    ];
//
//                    $inputCity =$newOrder->province;
//
//                    $city = $cityMap[$inputCity] ?? $inputCity;
//
//                    $subCityValue = null;
//                    if($newOrder->note_attributes) {
//                        $noteAttributes = json_decode($newOrder->note_attributes, true); // Decode JSON into an associative array
//
//                        foreach ($noteAttributes as $attribute) {
//                            if ($attribute['name'] === 'Sub City') {
//                                $subCityValue = $attribute['value'];
//                                break;
//                            }
//                        }
//                    }
//
//                    $block_value = null;
//                    if($newOrder->note_attributes_block_no) {
//                        $noteAttributes_block = json_decode($newOrder->note_attributes_block_no, true); // Decode JSON into an associative array
//
//
//
//                        foreach ($noteAttributes_block as $attribute) {
//                            if ($attribute['name'] === 'block_no') {
//                                $block_value = $attribute['value'];
//                                break;
//                            }
//                        }
//                    }
//
//
//                    $data = [
//                        'email' =>$email,
//                        'password' =>$password,
//                        'pick_up_address_id' =>$pick_up_address_id,
//                        'item_type' => 'ds',
//                        'invoice_id'=>$order_number,
//                        'item_price' => $newOrder->total_price,
//                        'payment_mode' => $payment_mode,
//                        'pickup_estimate_time_type' => 0,
//                        'pickup_estimate_time' => $pickupEstimateTime->format('d/m/Y h:i A'),
//                        'recipient_name' => $newOrder->shipping_name,
//                        'recipient_mobile' => $newOrder->phone,
////                        'address_area' => $newOrder->city,
//                        'address_area' => $subCityValue,
//                        'address_block_number' => $block_value,
//                        'address_street' => $newOrder->address1,
//                    ];
//
//                    // Build the cURL request
//                    $curl = curl_init();
//                    curl_setopt_array($curl, array(
//                        CURLOPT_URL => $url,
//                        CURLOPT_RETURNTRANSFER => true,
//                        CURLOPT_ENCODING => '',
//                        CURLOPT_MAXREDIRS => 10,
//                        CURLOPT_TIMEOUT => 0,
//                        CURLOPT_FOLLOWLOCATION => true,
//                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//                        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false),
//                        CURLOPT_CUSTOMREQUEST => 'POST',
//                        CURLOPT_POSTFIELDS => http_build_query($data), // Convert data to a query string
//                        CURLOPT_HTTPHEADER => array(
//                            'email: ' . $setting->email,
//                            'password: ' . $setting->password,
//                            'Content-Type: application/x-www-form-urlencoded',
//                        ),
//                    ));
//
//                    // Execute the cURL request
//                    $response = curl_exec($curl);
//
//                    // Close the cURL session
//                    curl_close($curl);
//
//                    // Decode the JSON response
//                    $responseData = json_decode($response, true);
//
//                    if ($responseData && $responseData['status']==1) {
//                        $deliveryId = $responseData['delivery_id'];
//                        $invoiceId = $responseData['invoice_id'];
//
//                        $newOrder->delivery_id=$deliveryId;
//                        $newOrder->invoice_id=$invoiceId;
//                        $newOrder->status=1;
//                        $newOrder->tryvengo_status='Pending';
//                        $newOrder->save();
//
//                    }
//
//
//                }

                if($setting->auto_push_orders==1) {
                    $setting_controller = new SettingController();
                    $setting_controller->createDhubOrder($newOrder);
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
                    $newOrder->province_code = $order->shipping_address->province_code;
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
                if (is_array($order->note_attributes) && count($order->note_attributes) > 0) {
                    // Initialize an array to hold the filtered attributes
                    $filteredAttributes = [];
                    $filtered_block_no_Attributes = [];
                    // Loop through the note_attributes to find the relevant one
                    foreach ($order->note_attributes as $attribute) {
                        if (isset($attribute->name) && $attribute->name === 'Sub City') {
                            // Add only the Sub City attribute to the filtered array
                            $filteredAttributes[] = $attribute;

                        }
                        if (isset($attribute->name) && $attribute->name === 'block_no') {
                            $filtered_block_no_Attributes[] = $attribute;

                        }
                    }

                    // If we found the Sub City attribute, save it to the new order
                    if (count($filteredAttributes) > 0) {
                        $newOrder->note_attributes = json_encode($filteredAttributes); // Store as JSON
                    }
                    if (count($filtered_block_no_Attributes) > 0) {
                        $newOrder->note_attributes_block_no = json_encode($filtered_block_no_Attributes); // Store as JSON
                    }
                }


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
            }


        }
    }


    public function SendOrderDelivery($id)
    {



        $shop = Auth::user();
        $order = Order::find($id);
        $setting=Setting::first();
        if($order){

try {


    $pickupEstimateTime = now()->addHours(4);

    if($order->phone){
        $phone=$order->phone;
    }elseif ($order->customer_phone){
        $phone=$order->customer_phone;
    }

    $subCityValue = null;
    if($order->note_attributes) {
        $noteAttributes = json_decode($order->note_attributes, true); // Decode JSON into an associative array



        foreach ($noteAttributes as $attribute) {
            if ($attribute['name'] === 'Sub City') {
                $subCityValue = $attribute['value'];
                break;
            }
        }
    }

    $block_value = null;
    if($order->note_attributes_block_no) {
        $noteAttributes_block = json_decode($order->note_attributes_block_no, true); // Decode JSON into an associative array



        foreach ($noteAttributes_block as $attribute) {
            if ($attribute['name'] === 'block_no') {
                $block_value = $attribute['value'];
                break;
            }
        }
    }


    $payment_type=1;
    $amount=$order->total_price;

    if($order->financial_status=='paid'){
        $payment_type=2;
        $amount=0;
    }

    $response = Http::withHeaders([
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjZCN0FDQzUyMDMwNUJGREI0RjcyNTJEQUVCMjE3N0NDMDkxRkFBRTEiLCJ4NXQiOiJhM3JNVWdNRnY5dFBjbExhNnlGM3pBa2ZxdUUiLCJ0eXAiOiJKV1QifQ.eyJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1laWRlbnRpZmllciI6IjhhMjA2MGYzLWU2ZTItNDUzYS1iMjYzLTMxZmI1YTUyNGYwZCIsImh0dHA6Ly9zY2hlbWFzLnhtbHNvYXAub3JnL3dzLzIwMDUvMDUvaWRlbnRpdHkvY2xhaW1zL25hbWUiOiJhaG1lZEtoYWxhZkBtdWJraGFyLmNvbSIsImh0dHA6Ly9zY2hlbWFzLnhtbHNvYXAub3JnL3dzLzIwMDUvMDUvaWRlbnRpdHkvY2xhaW1zL2VtYWlsYWRkcmVzcyI6ImFobWVkS2hhbGFmQG11YmtoYXIuY29tIiwiQXNwTmV0LklkZW50aXR5LlNlY3VyaXR5U3RhbXAiOiIyN09UNzM2SVVUWlRVQllLRzNLSFZFRVhFREVWTlRLUyIsIlVzZXJUeXBlIjoiMCIsIkxvZ2luVHlwZSI6IldlYiIsIlRlbmFudF9JZCI6IjhhMjA2MGYzLWU2ZTItNDUzYS1iMjYzLTMxZmI1YTUyNGYwZCIsIlBhcmVudFRlbmFudElkIjoiOTI4NTdhMDktMjQ0Ni00ODQzLWE2N2ItNWU4MjE1OWZiZDU0IiwiVGltZVpvbmVJZCI6IkFyYWIgU3RhbmRhcmQgVGltZSIsInBlcm1pc3Npb24iOlsiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suQ3JlYXRlVGFzayIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UYXNrLlVwZGF0ZVRhc2siLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5EZWxldGVUYXNrIiwiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suQ2FuY2VsVGFzayIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UYXNrLkNoYW5nZVRhc2tTdGF0dXMiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5SZWFkVW5hc3NpZ25lZFRhc2siLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5SZWFkVGFza3MiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5SZWFkVGFza0RldGFpbHMiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5FeHBvcnRUYXNrIiwiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suUmVjYWxsVGFzayIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UYXNrLkJ1bGtVcGxvYWQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5Bc3NpZ25lZE9yUmVhc3NpZ25UYXNrRHJpdmVyIiwiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suQXV0b0Fzc2lnbmVkT3JSZWFzc2lnblRhc2tEZWxpdmVyeUNvbXBhbnkiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuU2hvd0Rhc2hvYXJkIiwiTWFuYWdlclBlcm1pc3Npb25zLkFnZW50LlJlYWRBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5DcmVhdGVBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5VcGRhdGVBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5VcGRhdGVBbGxBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5EZWxldGVBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5DaGFuZ2VBZ2VudFBhc3N3b3JkIiwiTWFuYWdlclBlcm1pc3Npb25zLkFnZW50LlZpZXdEcml2ZXJzTG9naW5SZXF1ZXN0cyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5FeHBvcnRBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5JbXBvcnRBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UZWFtLkNyZWF0ZVRlYW0iLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGVhbS5VcGRhdGVUZWFtIiwiTWFuYWdlclBlcm1pc3Npb25zLlRlYW0uVXBkYXRlQWxsVGVhbSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UZWFtLkRlbGV0ZVRlYW0iLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGVhbS5EZWxldGVBbGxUZWFtIiwiTWFuYWdlclBlcm1pc3Npb25zLlRlYW0uUmVhZE15VGVhbSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5BZGRNYW5hZ2VyIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZU1hbmFnZXIiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuVXBkYXRlQWxsTWFuYWdlciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVUZWFtTWFuYWdlciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5EZWxldGVNYW5hZ2VyIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWRBbGxNYW5hZ2VycyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5SZWFkVGVhbU1hbmFnZXIiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuQ2hhbmdlTWFuYWdlclBhc3N3b3JkIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkFkZE1hbmFnZXJEaXNwYXRjaGluZyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVNYW5hZ2VyRGlzcGF0Y2hpbmciLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuRGVsZXRlTWFuYWdlckRpc3BhdGNoaW5nIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWRNYW5hZ2VyRGlzcGF0Y2hpbmciLCJNYW5hZ2VyUGVybWlzc2lvbnMuUm9sZXMuQWRkUm9sZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5Sb2xlcy5VcGRhdGVSb2xlIiwiTWFuYWdlclBlcm1pc3Npb25zLlJvbGVzLlVwZGF0ZUFsbFJvbGVzIiwiTWFuYWdlclBlcm1pc3Npb25zLlJvbGVzLkRlbGV0ZVJvbGUiLCJNYW5hZ2VyUGVybWlzc2lvbnMuUm9sZXMuUmVhZEFsbFJvbGVzIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkFkZEdlb2ZlbmNlIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZUdlb2ZlbmNlIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkRlbGV0ZUdlb2ZlbmNlIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWRHZW9mZW5jZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5FeHBvcnRHZW9GZW5jZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5BZGRSZXN0YXVyYW50IiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZVJlc3RhdXJhbnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuRGVsZXRlUmVzdGF1cmFudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5SZWFkUmVzdGF1cmFudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5CbG9ja1Jlc3RhdXJhbnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuVW5CbG9ja1Jlc3RhdXJhbnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuQWRkQnJhbmNoIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZUJyYW5jaCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5EZWxldGVCcmFuY2giLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuUmVhZEJyYW5jaCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5CbG9ja0JyYW5jaCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VbkJsb2NrQnJhbmNoIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkJyYW5jaERlbGl2ZXJ5Q2hhcmdlIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLkNyZWF0ZUN1c3RvbWVyIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLkRlbGV0ZUN1c3RvbWVyIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLlVwZGF0ZUN1c3RvbWVyIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLlJlYWRDdXN0b21lciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5DdXN0b21lci5FeHBvcnRDdXN0b21lciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5DdXN0b21lci5JbXBvcnRDdXN0b21lciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVBdXRvQWxsb2NhdGlvbiIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVHZW5lcmFsIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWROb3RpZmljYXRpb24iLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuVXBkYXRlTm90aWZpY2F0aW9uU2V0dGluZyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5FZGl0Tm90aWZpY2F0aW9uTWVzc2FnZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5FZGl0UHJvZmlsZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5FZGl0RGVsaXZlcnlXb3JraW5nSG91cnMiLCJNYW5hZ2VyUGVybWlzc2lvbnMuRWRpdFRpbWVPdXRTZXR0aW5ncyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5FbmFibGVTaGlwcG1lbnRCdWxrVXBsb2FkIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlNNU0xvZyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5SZXBvcnRzLlZpZXdSZXBvcnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuUmVwb3J0cy5WaWV3TWFuYWdlclJlcG9ydCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5SZXBvcnRzLkV4cG9ydFJlcG9ydCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5SZXBvcnRzLkV4cG9ydE1hbmFnZXJSZXBvcnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuQWNjb3VudExvZ3MuVmlld0FjY291bnRMb2dzIiwiTWFuYWdlclBlcm1pc3Npb25zLkFjY291bnRMb2dzLkV4cG9ydEFjY291bnRMb2dzIiwiTWFuYWdlclBlcm1pc3Npb25zLkFnZW50LlJlYWRQbGF0Zm9ybUFnZW50Il0sInNjb3BlIjoidGVuYW50IiwibmJmIjoxNzMwNjMzNTQxLCJleHAiOjE3Mzg1ODIzNDAsImlzcyI6IkRNUyIsImF1ZCI6IkRNU0NsaWVudCJ9.l5Ak8_NyQo7nNZsnVGyqDHzrrzgNVfa8PBFrnTXVIFausufbT1VHSvKpk0D49dXwJ2pXB50jq0rbcVPIUY-zHeXuzsoczzYE9m8pZ90_i9Yt-RohpQoLKwzrQ2EtV0CYrfhN51NDNkfXxZ31VvfLrpvU26ZvHGtvR4wAav5OO3j9rI7HEltkc_uMI3Igu5S4YmAQNpSUZ4ZXATT1_XDGDJE7otJr5XdxwsM4yQXV4RpUksYM8j-K3jY28EWa2p1U3hO4sK0Hv9PLs2I3wdvm-VZLuqgidITABkgEyoNt2AFr3xj4H0QFfcjt9CMY25A8SEm2AD-p7AliUILJsL0Tug',
    ])->post('https://staging.dhub.pro/external/api/Order/Create', [
        "isPerishable" => true,
        "tasks" => [
            [
                "taskTypeId" => 1,
                "branchId"=>133,
                "description" =>$order->note,
                "date" => $order->created_at,
                "orderId" => $order->order_number,
                "customer" => [
                    "name" => "Around",
                    "phone" => '56565601',
//                        "countryCode" => "965",
                    "address" => 'Block 2, 290, front of fexily,industrial ardiya',
                    "latitude" => 29.291472889930347,
                    "longitude" => 47.92286361797812,
                ]
            ],
            [
                "paymentType" => $payment_type,
                "totalAmount" => $amount,
                "amountToCollect" => $amount,
                "taskTypeId" => 2,
                "description" => $order->note,
                "date" =>$pickupEstimateTime,
                "customer" => [
                    "name" => $order->first_name. $order->last_name,
                    "phone" => $phone,
//                        "countryCode" => "965",
                    "address" => $order->address1.','.$block_value.','.$subCityValue,
//                        "latitude" => 29.3310541,
//                        "longitude" => 47.9198454,
                ]
            ]
        ]
    ]);

    // Check if request was successful
    if ($response->successful()) {

        $responseData = json_decode($response, true);

        if ($responseData && $responseData['status']==200) {


            $deliveryId = $responseData['data']['id'];
            $order->status=1;
            $order->tryvengo_status='Unassigned';
            $order->dhub_id=$deliveryId;
            $order->save();
            return Redirect::tokenRedirect('home', ['notice' => 'Order Pushed to Dhub Successfully']);
        }

    } else {

        $log=new Log();
        $log->type='Order dhub';
        $log->error=json_encode($response);
        $log->save();
        // Handle errors
        return Redirect::tokenRedirect('home', ['error' => $response->body()]);
    }


}catch (\Exception $exception){

    dd($exception->getMessage());
}
        }

    }


    private function findBestMatch($cityName, $cityList)
    {
        $bestMatch = null;
        $highestScore = 0;

        foreach ($cityList as $city) {
            similar_text($cityName, $city, $similarity);
            if ($similarity > $highestScore) {
                $highestScore = $similarity;
                $bestMatch = $city;
            }
        }

        return $bestMatch;
        return [
            'name' => $bestMatch,
//            'score' => $highestScore
        ];
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
        $pending_orders=Order::whereIn('id',$order_ids)->where('tryvengo_status','Unassigned')->count();
        $delivered_orders=Order::whereIn('id',$order_ids)->where('tryvengo_status','Successful')->count();



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


                $pickupEstimateTime = now()->addHours(4);

                if($order->phone){
                    $phone=$order->phone;
                }elseif ($order->customer_phone){
                    $phone=$order->customer_phone;
                }

                $subCityValue = null;
                if($order->note_attributes) {
                    $noteAttributes = json_decode($order->note_attributes, true); // Decode JSON into an associative array



                    foreach ($noteAttributes as $attribute) {
                        if ($attribute['name'] === 'Sub City') {
                            $subCityValue = $attribute['value'];
                            break;
                        }
                    }
                }

                $block_value = null;
                if($order->note_attributes_block_no) {
                    $noteAttributes_block = json_decode($order->note_attributes_block_no, true); // Decode JSON into an associative array



                    foreach ($noteAttributes_block as $attribute) {
                        if ($attribute['name'] === 'block_no') {
                            $block_value = $attribute['value'];
                            break;
                        }
                    }
                }

                $payment_type=1;
                $amount=$order->total_price;

                if($order->financial_status=='paid'){
                    $payment_type=2;
                    $amount=0;
                }

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IjZCN0FDQzUyMDMwNUJGREI0RjcyNTJEQUVCMjE3N0NDMDkxRkFBRTEiLCJ4NXQiOiJhM3JNVWdNRnY5dFBjbExhNnlGM3pBa2ZxdUUiLCJ0eXAiOiJKV1QifQ.eyJodHRwOi8vc2NoZW1hcy54bWxzb2FwLm9yZy93cy8yMDA1LzA1L2lkZW50aXR5L2NsYWltcy9uYW1laWRlbnRpZmllciI6IjhhMjA2MGYzLWU2ZTItNDUzYS1iMjYzLTMxZmI1YTUyNGYwZCIsImh0dHA6Ly9zY2hlbWFzLnhtbHNvYXAub3JnL3dzLzIwMDUvMDUvaWRlbnRpdHkvY2xhaW1zL25hbWUiOiJhaG1lZEtoYWxhZkBtdWJraGFyLmNvbSIsImh0dHA6Ly9zY2hlbWFzLnhtbHNvYXAub3JnL3dzLzIwMDUvMDUvaWRlbnRpdHkvY2xhaW1zL2VtYWlsYWRkcmVzcyI6ImFobWVkS2hhbGFmQG11YmtoYXIuY29tIiwiQXNwTmV0LklkZW50aXR5LlNlY3VyaXR5U3RhbXAiOiIyN09UNzM2SVVUWlRVQllLRzNLSFZFRVhFREVWTlRLUyIsIlVzZXJUeXBlIjoiMCIsIkxvZ2luVHlwZSI6IldlYiIsIlRlbmFudF9JZCI6IjhhMjA2MGYzLWU2ZTItNDUzYS1iMjYzLTMxZmI1YTUyNGYwZCIsIlBhcmVudFRlbmFudElkIjoiOTI4NTdhMDktMjQ0Ni00ODQzLWE2N2ItNWU4MjE1OWZiZDU0IiwiVGltZVpvbmVJZCI6IkFyYWIgU3RhbmRhcmQgVGltZSIsInBlcm1pc3Npb24iOlsiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suQ3JlYXRlVGFzayIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UYXNrLlVwZGF0ZVRhc2siLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5EZWxldGVUYXNrIiwiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suQ2FuY2VsVGFzayIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UYXNrLkNoYW5nZVRhc2tTdGF0dXMiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5SZWFkVW5hc3NpZ25lZFRhc2siLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5SZWFkVGFza3MiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5SZWFkVGFza0RldGFpbHMiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5FeHBvcnRUYXNrIiwiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suUmVjYWxsVGFzayIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UYXNrLkJ1bGtVcGxvYWQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGFzay5Bc3NpZ25lZE9yUmVhc3NpZ25UYXNrRHJpdmVyIiwiTWFuYWdlclBlcm1pc3Npb25zLlRhc2suQXV0b0Fzc2lnbmVkT3JSZWFzc2lnblRhc2tEZWxpdmVyeUNvbXBhbnkiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuU2hvd0Rhc2hvYXJkIiwiTWFuYWdlclBlcm1pc3Npb25zLkFnZW50LlJlYWRBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5DcmVhdGVBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5VcGRhdGVBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5VcGRhdGVBbGxBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5EZWxldGVBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5DaGFuZ2VBZ2VudFBhc3N3b3JkIiwiTWFuYWdlclBlcm1pc3Npb25zLkFnZW50LlZpZXdEcml2ZXJzTG9naW5SZXF1ZXN0cyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5FeHBvcnRBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5BZ2VudC5JbXBvcnRBZ2VudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UZWFtLkNyZWF0ZVRlYW0iLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGVhbS5VcGRhdGVUZWFtIiwiTWFuYWdlclBlcm1pc3Npb25zLlRlYW0uVXBkYXRlQWxsVGVhbSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5UZWFtLkRlbGV0ZVRlYW0iLCJNYW5hZ2VyUGVybWlzc2lvbnMuVGVhbS5EZWxldGVBbGxUZWFtIiwiTWFuYWdlclBlcm1pc3Npb25zLlRlYW0uUmVhZE15VGVhbSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5BZGRNYW5hZ2VyIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZU1hbmFnZXIiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuVXBkYXRlQWxsTWFuYWdlciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVUZWFtTWFuYWdlciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5EZWxldGVNYW5hZ2VyIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWRBbGxNYW5hZ2VycyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5SZWFkVGVhbU1hbmFnZXIiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuQ2hhbmdlTWFuYWdlclBhc3N3b3JkIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkFkZE1hbmFnZXJEaXNwYXRjaGluZyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVNYW5hZ2VyRGlzcGF0Y2hpbmciLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuRGVsZXRlTWFuYWdlckRpc3BhdGNoaW5nIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWRNYW5hZ2VyRGlzcGF0Y2hpbmciLCJNYW5hZ2VyUGVybWlzc2lvbnMuUm9sZXMuQWRkUm9sZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5Sb2xlcy5VcGRhdGVSb2xlIiwiTWFuYWdlclBlcm1pc3Npb25zLlJvbGVzLlVwZGF0ZUFsbFJvbGVzIiwiTWFuYWdlclBlcm1pc3Npb25zLlJvbGVzLkRlbGV0ZVJvbGUiLCJNYW5hZ2VyUGVybWlzc2lvbnMuUm9sZXMuUmVhZEFsbFJvbGVzIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkFkZEdlb2ZlbmNlIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZUdlb2ZlbmNlIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkRlbGV0ZUdlb2ZlbmNlIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWRHZW9mZW5jZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5FeHBvcnRHZW9GZW5jZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5BZGRSZXN0YXVyYW50IiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZVJlc3RhdXJhbnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuRGVsZXRlUmVzdGF1cmFudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5SZWFkUmVzdGF1cmFudCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5CbG9ja1Jlc3RhdXJhbnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuVW5CbG9ja1Jlc3RhdXJhbnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuQWRkQnJhbmNoIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlVwZGF0ZUJyYW5jaCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5EZWxldGVCcmFuY2giLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuUmVhZEJyYW5jaCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5CbG9ja0JyYW5jaCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VbkJsb2NrQnJhbmNoIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLkJyYW5jaERlbGl2ZXJ5Q2hhcmdlIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLkNyZWF0ZUN1c3RvbWVyIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLkRlbGV0ZUN1c3RvbWVyIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLlVwZGF0ZUN1c3RvbWVyIiwiTWFuYWdlclBlcm1pc3Npb25zLkN1c3RvbWVyLlJlYWRDdXN0b21lciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5DdXN0b21lci5FeHBvcnRDdXN0b21lciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5DdXN0b21lci5JbXBvcnRDdXN0b21lciIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVBdXRvQWxsb2NhdGlvbiIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5VcGRhdGVHZW5lcmFsIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlJlYWROb3RpZmljYXRpb24iLCJNYW5hZ2VyUGVybWlzc2lvbnMuU2V0dGluZ3MuVXBkYXRlTm90aWZpY2F0aW9uU2V0dGluZyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5FZGl0Tm90aWZpY2F0aW9uTWVzc2FnZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5FZGl0UHJvZmlsZSIsIk1hbmFnZXJQZXJtaXNzaW9ucy5FZGl0RGVsaXZlcnlXb3JraW5nSG91cnMiLCJNYW5hZ2VyUGVybWlzc2lvbnMuRWRpdFRpbWVPdXRTZXR0aW5ncyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5TZXR0aW5ncy5FbmFibGVTaGlwcG1lbnRCdWxrVXBsb2FkIiwiTWFuYWdlclBlcm1pc3Npb25zLlNldHRpbmdzLlNNU0xvZyIsIk1hbmFnZXJQZXJtaXNzaW9ucy5SZXBvcnRzLlZpZXdSZXBvcnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuUmVwb3J0cy5WaWV3TWFuYWdlclJlcG9ydCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5SZXBvcnRzLkV4cG9ydFJlcG9ydCIsIk1hbmFnZXJQZXJtaXNzaW9ucy5SZXBvcnRzLkV4cG9ydE1hbmFnZXJSZXBvcnQiLCJNYW5hZ2VyUGVybWlzc2lvbnMuQWNjb3VudExvZ3MuVmlld0FjY291bnRMb2dzIiwiTWFuYWdlclBlcm1pc3Npb25zLkFjY291bnRMb2dzLkV4cG9ydEFjY291bnRMb2dzIiwiTWFuYWdlclBlcm1pc3Npb25zLkFnZW50LlJlYWRQbGF0Zm9ybUFnZW50Il0sInNjb3BlIjoidGVuYW50IiwibmJmIjoxNzMwNjMzNTQxLCJleHAiOjE3Mzg1ODIzNDAsImlzcyI6IkRNUyIsImF1ZCI6IkRNU0NsaWVudCJ9.l5Ak8_NyQo7nNZsnVGyqDHzrrzgNVfa8PBFrnTXVIFausufbT1VHSvKpk0D49dXwJ2pXB50jq0rbcVPIUY-zHeXuzsoczzYE9m8pZ90_i9Yt-RohpQoLKwzrQ2EtV0CYrfhN51NDNkfXxZ31VvfLrpvU26ZvHGtvR4wAav5OO3j9rI7HEltkc_uMI3Igu5S4YmAQNpSUZ4ZXATT1_XDGDJE7otJr5XdxwsM4yQXV4RpUksYM8j-K3jY28EWa2p1U3hO4sK0Hv9PLs2I3wdvm-VZLuqgidITABkgEyoNt2AFr3xj4H0QFfcjt9CMY25A8SEm2AD-p7AliUILJsL0Tug',
                ])->post('https://staging.dhub.pro/external/api/Order/Create', [
                    "isPerishable" => true,
                    "tasks" => [
                        [
                            "taskTypeId" => 1,
                            "branchId"=>133,
                            "description" =>$order->note,
                            "date" => $order->created_at,
                            "orderId" => $order->order_number,
                            "customer" => [
                                "name" => "Around",
                                "phone" => '56565601',
//                        "countryCode" => "965",
                                "address" => 'Block 2, 290, front of fexily,industrial ardiya',
                                "latitude" => 29.291472889930347,
                                "longitude" => 47.92286361797812,
                            ]
                        ],
                        [
                            "paymentType" => $payment_type,
                            "totalAmount" => $amount,
                            "amountToCollect" => $amount,
                            "taskTypeId" => 2,
                            "description" => $order->note,
                            "date" =>$pickupEstimateTime,
                            "customer" => [
                                "name" => $order->first_name. $order->last_name,
                                "phone" => $phone,
//                        "countryCode" => "965",
                                "address" => $order->address1.','.$block_value.','.$subCityValue,
//                        "latitude" => 29.3310541,
//                        "longitude" => 47.9198454,
                            ]
                        ]
                    ]
                ]);

                // Check if request was successful
                if ($response->successful()) {

                    $responseData = json_decode($response, true);

                    if ($responseData && $responseData['status']==200) {


                        $deliveryId = $responseData['data']['id'];
                        $order->status=1;
                        $order->tryvengo_status='Unassigned';
                        $order->dhub_id=$deliveryId;
                        $order->save();

                    }

                } else {

                    $log=new Log();
                    $log->type='Order dhub';
                    $log->error=json_encode($response);
                    $log->save();

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


    public function findBestMatch1(Request $request)
    {
        $cityMap = [
            'Al Ahmadi' => 'Ahmadi',
            'Al Farwaniyah' => 'Al Farwaniyah',
            'Hawalli' => 'Hawally',
            'Mubarak Al-Kabeer' => 'Mubarak Al Kabeer',
            'Al Jahra' => 'Al Jahra',
            'Al Asimah' => 'Kuwait City',
        ];

        $inputCity = $request->input('city', 'Al Asimah');

        $outputCity = $cityMap[$inputCity] ?? $inputCity;

        dd( $outputCity); // Outputs "Kuwait City" if $inputCity is "Al Asimah"


    }





}
