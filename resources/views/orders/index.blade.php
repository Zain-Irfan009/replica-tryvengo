@extends('layout.index')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .sync-button{
        float: right;
    }
    .view{
        font-size: 10px !important;
    }
    .select2-container{
        width: 100% !important;
    }
    .size_button{
        font-size: 13px !important;
    }
    .btn_size{
        font-size: 12px !important;
    }
    .input-group > :not(:first-child):not(.dropdown-menu):not(.valid-tooltip):not(.valid-feedback):not(.invalid-tooltip):not(.invalid-feedback){
        margin-left: 2px !important;
    }
    .display_none
    {
        display: none;
    }
    .pop_up{
        display: none;
    }
    span.select2.select2-container.select2-container--default {
        width: 150px !important;
        padding: 2px;
        background-color: #fff;
        border: 1px solid #dadcde;
    }
    .theme-light{
        overflow-x: hidden;
    }
    .error-alert{
        background: #d94343 !important;
        color: white !important;
    }
    .error_icon{
        color: white !important;
    }

</style>
@section('content')
    @if(session()->has('message'))
        <div class="alert alert-important alert-success alert-dismissible "  role="alert" id="alertSuccess">
            <div class="d-flex">
                <div>
                    <!-- Download SVG icon from http://tabler-icons.io/i/check -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>
                </div>
                <div id="alertSuccessText">
                    {{ session()->get('message') }}
                </div>
            </div>
            {{--            <a class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="close"></a>--}}
        </div>
    @endif
    @if(session()->has('error'))
        <div class="alert  alert-error error-alert alert-dismissible "  role="alert" id="alertSuccess2">
            <div class="d-flex">
                <div>
                    <!-- Download SVG icon from http://tabler-icons.io/i/check -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon error_icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>
                </div>
                <div id="alertSuccessText">
                    {{ session()->get('error') }}
                </div>
            </div>
            {{--            <a class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="close"></a>--}}
        </div>
    @endif

    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">
                <h1 class="page-title">
                    Summary
                </h1>

            </div>
        </div>



        <div class="row mt-3">
            <div class="col-6">
                <strong>Total Orders: </strong>{{$total_orders}}
            </div>

            <div class="col-6">
                <strong>Orders Pending: </strong> {{$pending_orders}}
            </div>

            <div class="col-6">
                <strong>Orders Pushed: </strong> {{$pushed_orders}}
            </div>

            <div class="col-6">
               <strong>Orders Delivered: </strong> {{$delivered_orders}}
            </div>
        </div>
    </div>



    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col-md-3">

                <h1 class="page-title">
                    Orders
                </h1>
            </div>
            <div class="col-md-9">

                <div class="form-group">
                    <form action="{{route('orders.filter')}}" method="get">
                        @sessionToken
                        <div class="input-group">

                            <select class="form-control " name="tryvengo_status">
                                <option value="">Select Tryvengo Status</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Pending') selected @endif value="Pending">Pending</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Confirm') selected @endif value="Confirm">Confirm</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Pick up in Progress') selected @endif value="Pick up in Progress">Pick up in Progress</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Reached Pickup Location') selected @endif value="Reached Pickup Location">Reached Pickup Location</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Picked') selected @endif value="Picked">Picked</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Out For Delivery') selected @endif value="Out For Delivery">Out For Delivery</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Reached Delivery Location') selected @endif value="Reached Delivery Location">Reached Delivery Location</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Delivered') selected @endif value="Delivered">Delivered</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Cancel') selected @endif value="Cancel">Cancel</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Rescheduled') selected @endif value="Rescheduled">Rescheduled</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Reject') selected @endif value="Reject">Reject</option>
                                <option @if(isset($request) && $request->input('tryvengo_status')=='Return') selected @endif value="Return">Return</option>

                            </select>



                            <select class="form-control " name="order_status">
                                <option value="">Select Order Status</option>
                                <option @if(isset($request) && $request->input('order_status')=="0") selected @endif value="0">Not Pushed</option>
                                <option @if(isset($request) && $request->input('order_status')=="1") selected @endif value="1">Pushed</option>
                            </select>

                            <input placeholder="Enter Order Number,Customer Name" type="text" @if (isset($request)) value="{{$request->orders_filter}}" @endif name="orders_filter" id="question_email" autocomplete="off" class="form-control">

                            <input type="date" value="{{ isset($request) ? $request->date_filter : '' }}" name="date_filter" id="question_email" autocomplete="off" class="form-control">
                            @if(isset($request))
                                <a href="{{env('TOKEN_URL').route('home')}}" type="button" class="btn btn-secondary clear_filter_data mr-1 pl-4 pr-4">Clear</a>
                            @endif
                            <button type="submit" class="btn btn-primary mr-1 pl-4 pr-4">Filter</button>
                            <a href="{{URL::tokenRoute('sync.orders')}}" type="button" class="btn sync-button btn-primary size_button ml-1">Sync Orders</a>
                            <a href="#"  type="button" style="display: none;float: right" class="btn export_button btn-primary size_button mx-2 ">Push Selected</a>
                        </div>
                    </form>
                </div>


            </div>




        </div>
    </div>



    <div class="page-body">
        <div class="">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="table-responsive">
                            @if (count($orders) > 0)
                                <table
                                    class="table table-vcenter card-table">
                                    <thead>
                                    <tr>
                                        <th><input class="form-check-input" id="checkAll" value=""  type="checkbox"></th>
                                        <th>Order Num#</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th >Total Price</th>
                                        <th>Status</th>
                                        <th >Tryvengo Status</th>
                                        <th style="width: 20% !important;">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>

                                    @foreach($orders as $order)
                                        @php
                                                $tags=explode(',',$order->tags);
                                        @endphp
                                        <tr  class="product_detail" data-row_id="#varinat_details_{{$order->id}}">
                                            <td><input class="form-check-input single_check" value="{{$order->id}}"  type="checkbox"></td>
                                            <td> {{$order->order_number}}</td>
                                            <td>{{$order->created_at->format('F d')}}</td>
                                            <td class="text-muted" >
                                                {{$order->shipping_name}}
                                            </td>



                                            <td>{{$order->currency}} {{$order->total_price}}</td>
                                            <td>
                                                @if($order->status==0)
                                                <span class="badge bg-danger">Not Pushed</span>
                                                @else
                                                    <span class="badge bg-success">Pushed</span>
                                                @endif
                                            </td>

                                            <td>
                                                @if($order->tryvengo_status)
                                                <span class="badge bg-primary">{{$order->tryvengo_status}}</span>
                                                    @endif
                                            </td>
                                            <td>
                                            @if($order->status==0)
                                                <a href="{{URL::tokenRoute('send.order.delivery',$order->id)}}" class="btn btn-primary view">Push to Tryvengo</a>
                                            @endif
                                            </td>






                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            @else
                                <h3 class="mx-3 my-3">No Orders Found</h3>
                            @endif

                            <div class="pagination">
                                {{ $orders->appends(\Illuminate\Support\Facades\Request::except('page'))->links("pagination::bootstrap-4") }}
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <form id="export_form" method="post" action="{{route('push.selected.orders')}}" >
        @sessionToken
        <input type="hidden" id="order_ids" name="order_ids" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>

        $(document).ready(function (){

            $('.export_button').click(function(){
                $('#export_form').submit();
            });

            setTimeout(function() { $(".alert-success").hide(); }, 2000);
            setTimeout(function() { $(".error-alert").hide(); }, 2000);
            $('.js-example').select2();

            $('select[name=date]').on('change',function(){
                if($(this).val()=='custom')
                {
                    $('.date_range').removeClass('display_none');
                }else
                {
                    $('.date_range').addClass('display_none');
                }
            });


            $('body').on('click','.single_check',function(){


                if($('.single_check:checked').length >0){

                    $('.export_button').show();
                }
                else{
                    $('.export_button').hide();
                }
                var val = [];
                $('.single_check:checked').each(function(i){
                    val[i] = $(this).val();
                });


                var order_ids= val.join(',');
                $('#order_ids').val(order_ids);

            });



            $('body').on('click','.submit_loader',function (){
                $('body').append('<div class="LockOn"> </div>');
            });


            $("#checkAll").change(function(){

                if($('#checkAll').prop('checked')) {
                    $('.single_check').prop('checked', true)
                    $('.export_button').show();
                    var val = [];
                    $('.single_check:checked').each(function(i){
                        val[i] = $(this).val();
                    });

                    var order_ids= val.join(',');
                    $('#order_ids').val(order_ids);

                }else {
                    $('.single_check').prop('checked', false);
                    $('.export_button').hide();
                    $('#order_ids').val('');
                }
            });
        })
    </script>
@endsection

