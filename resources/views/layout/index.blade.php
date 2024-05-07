<!doctype html>
<div lang="en">
@include('layout.head')

<style>
    .alert{
        padding: 4px;
        padding-top: 10px;
    }
</style>

    <?php

    header("Content-Security-Policy: frame-ancestors https://".\Illuminate\Support\Facades\Auth::user()->name." https://admin.shopify.com;");
    ?>
</head>
<div class="page">
<body class="antialiased">
<div class="wrapper">
    @include('layout.navbar')

    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
                <div class="row row-deck row-cards">
                    <div class="alert alert-important alert-success alert-dismissible " style="display: none" role="alert" id="alertSuccess">
                        <div class="d-flex">
                            <div>
                                <!-- Download SVG icon from http://tabler-icons.io/i/check -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon alert-icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>
                            </div>
                            <div id="alertSuccessText">

                            </div>
                        </div>
                        {{--            <a class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="close"></a>--}}
                    </div>

                    @yield('content')

                </div>


            </div>
        </div>
    </div>

</div>

</div>

    <footer class="footer footer-transparent d-print-none">
        <div class="container-xl">
            <div class="row text-center align-items-center ">
            <div class="col-4"></div>
                <div class="col-6 col-lg-auto mt-3 mt-lg-0">
                    <ul class="list-inline list-inline-dots mb-0">
{{--                        <li class="list-inline-item">--}}
{{--                          Contact Us at--}}
{{--                            <a href="" class="link-secondary">info@haligone.com or 902-635-4109</a>--}}
{{--                        </li>--}}

                    </ul>
                </div>
            </div>
        </div>
    </footer>
</div>
{{--@if(\Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_enabled'))--}}

{{--    <script--}}
{{--        src="https://unpkg.com/@shopify/app-bridge{{ \Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>--}}
{{--    <script--}}
{{--        src="https://unpkg.com/@shopify/app-bridge-utils{{ \Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>--}}

{{--    @php--}}

{{--        $host = base64_decode(session('host'));--}}

{{--          if (str_contains($host, 'admin.shopify.com') || $host==''){--}}
{{--              $shopOrigin= 'admin.shopify.com';--}}
{{--          }else{--}}
{{--              $shopOrigin=Auth::user()->name;--}}
{{--          }--}}

{{--    @endphp--}}
{{--    <script--}}
{{--        @if(\Osiset\ShopifyApp\Util::getShopifyConfig('turbo_enabled'))--}}
{{--        data-turbolinks-eval="false"--}}
{{--        @endif--}}
{{--    >--}}
{{--        var AppBridge = window['app-bridge'];--}}
{{--        var actions = AppBridge.actions;--}}
{{--        var utils = window['app-bridge-utils'];--}}
{{--        var createApp = AppBridge.default;--}}
{{--        var app = createApp({--}}
{{--            apiKey: "{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key', $shopDomain ?? Auth::user()->name ) }}",--}}
{{--            shopOrigin: "{{ $shopOrigin}}",--}}
{{--            // shopOrigin: "admin.shopify.com",--}}
{{--            --}}{{--host: "{{ \Request::get('host') }}",--}}
{{--            host: "{{ session('host') }}",--}}
{{--            forceRedirect: true,--}}
{{--        });--}}
{{--    </script>--}}

{{--    @include('shopify-app::partials.token_handler')--}}
{{--    @include('shopify-app::partials.flash_messages')--}}
{{--@endif--}}



{{--@if(\Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_enabled'))--}}
{{--    <script--}}
{{--        src="https://unpkg.com/@shopify/app-bridge{{ \Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>--}}
{{--    <script--}}
{{--        src="https://unpkg.com/@shopify/app-bridge-utils{{ \Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>--}}
{{--    <script--}}
{{--        @if(\Osiset\ShopifyApp\Util::getShopifyConfig('turbo_enabled'))--}}
{{--        data-turbolinks-eval="false"--}}
{{--        @endif--}}
{{--    >--}}
{{--        var AppBridge = window['app-bridge'];--}}
{{--        var actions = AppBridge.actions;--}}
{{--        var utils = window['app-bridge-utils'];--}}
{{--        var createApp = AppBridge.default;--}}
{{--        var app = createApp({--}}
{{--            apiKey: "{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key', $shopDomain ?? Auth::user()->name ) }}",--}}
{{--            shopOrigin: "{{ $shopDomain ?? Auth::user()->name }}",--}}
{{--            host: "{{ \Request::get('host') }}",--}}
{{--            forceRedirect: true,--}}
{{--        });--}}
{{--    </script>--}}

{{--    @include('shopify-app::partials.token_handler')--}}
{{--    @include('shopify-app::partials.flash_messages')--}}
{{--@endif--}}


@if(\Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_enabled'))

    <script
        src="https://unpkg.com/@shopify/app-bridge{{ \Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>
    <script
        src="https://unpkg.com/@shopify/app-bridge-utils{{ \Osiset\ShopifyApp\Util::getShopifyConfig('appbridge_version') ? '@'.config('shopify-app.appbridge_version') : '' }}"></script>

    @php

        $host = base64_decode(session('host'));

          if (str_contains($host, 'admin.shopify.com') || $host==''){
              $shopOrigin= 'admin.shopify.com';
          }else{
              $shopOrigin=Auth::user()->name;
          }

    @endphp
    <script
        @if(\Osiset\ShopifyApp\Util::getShopifyConfig('turbo_enabled'))
        data-turbolinks-eval="false"
        @endif
    >
        var AppBridge = window['app-bridge'];
        var actions = AppBridge.actions;
        var utils = window['app-bridge-utils'];
        var createApp = AppBridge.default;
        var app = createApp({
            apiKey: "{{ \Osiset\ShopifyApp\Util::getShopifyConfig('api_key', $shopDomain ?? env('SHOP_NAME') ) }}",
            shopOrigin: "{{ $shopOrigin}}",
            // shopOrigin: "admin.shopify.com",
            {{--host: "{{ \Request::get('host') }}",--}}
            host: "{{ session('host') }}",
            forceRedirect: true,
        });
    </script>

    @include('shopify-app::partials.token_handler')
    @include('shopify-app::partials.flash_messages')
@endif
<script>

    $('body').on('click','.submit_loader',function (){


        $('body').append('<div id="coverScreen"  class="LockOn"> </div>');


    });

</script>

<script src="{{asset('dist/js/demo.min.js')}}" defer></script>
<script src="{{asset('dist/js/tabler.min.js')}}" defer></script>
@yield('scripts')

</body>
</html>
