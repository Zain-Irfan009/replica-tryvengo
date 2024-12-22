<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;

class SettingController extends Controller
{
    public function Settings(){

        $setting=Setting::first();
        return view('settings.index',compact('setting'));
    }

    public function SettingsSave(Request $request){

        $settings=Setting::first();
        if($settings==null){
            $settings=new Setting();
        }

        $settings->auto_push_orders=isset($request->auto_push_orders)?$request->auto_push_orders:0;
//        $settings->email=$request->email;
//        $settings->password=$request->password;
//        $settings->email2=$request->email2;
//        $settings->password2=$request->password2;
//        $settings->switch_account=isset($request->switch_account)?$request->switch_account:0;
        $settings->save();
        return Redirect::tokenRedirect('settings', ['notice' => 'Settings Save Successfully']);
    }


    public function createDhubOrder($order)
    {


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
            // Handle errors
            return response()->json([
                'error' => $response->body(),
            ], $response->status());
        }
    }
}
