@extends('layout.index')
<style>
    .sync-button{
        float: right;
        margin-right: 1px;
    }
    .setting_heading{
        text-decoration: underline;
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
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col-md-6">

                <h1 class="page-title">
                    Settings
                </h1>
            </div>

        </div>
    </div>

    <div class="page-body">
        <div class="">



                <div class="row row-cards">
                    <div class="col-12">
                        <form method="post" action="{{route('settings.save')}}">
                            @sessionToken
                            <div class="card">
                                <div class="card-body">

                                    <div class="row">
                                        <div class="col-12">
                                            <h3 style="text-decoration: underline;">1st Account</h3>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <div class="form-label">Email</div>
                                            <input type="email" name="email" required class="form-control" value="@if(isset($setting->email)){{$setting->email}}@endif"  required/>
                                        </div>

                                        <div class="col-6 ">
                                            <div class="form-label">Password</div>
                                            <input type="text" name="password" required value="@if(isset($setting->password)){{$setting->password}}@endif"  required class="form-control"  autocomplete="off"/>
                                        </div>

                                    </div>


                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <h3 style="text-decoration: underline;">2nd Account</h3>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <div class="form-label">Email</div>
                                            <input type="email" name="email2" required class="form-control" value="@if(isset($setting->email2)){{$setting->email2}}@endif"  required/>
                                        </div>

                                        <div class="col-6 ">
                                            <div class="form-label">Password</div>
                                            <input type="text" name="password2" required value="@if(isset($setting->password2)){{$setting->password2}}@endif"  required class="form-control"  autocomplete="off"/>
                                        </div>

                                        <div class="col-4 mt-4">
                                            <label class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="auto_push_orders" @if(isset($setting) && $setting->auto_push_orders == 1) checked @endif value="1">
                                                <span class="form-check-label">Auto Push Orders</span>
                                            </label>
                                        </div>

                                        <div class="col-4 mt-4">
                                            <label class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="switch_account" @if(isset($setting) && $setting->switch_account == 1) checked @endif value="1">
                                                <span class="form-check-label">2nd Account</span>
                                            </label>
                                        </div>
                                    </div>


                                </div>



                                <div class="row mt-3">
                                    <div class="col-6"></div>
                                    <div class="col-6">
                                        <button type="submit" class="btn sync-button btn-primary mx-4 mb-3">Save</button>

                                    </div>
                                </div>

                            </div>
                    </div>
                    </form>
                </div>

        </div>





    </div>
    </div>

    <script>

        $(document).ready(function(){

            setTimeout(function() { $(".alert-success").hide(); }, 2000);
        });

    </script>
@endsection

