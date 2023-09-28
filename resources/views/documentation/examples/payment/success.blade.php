@extends('layouts.app', ['body_class' => 'woocommerce-order-received'])

@section('header')
    @include('partials.sections.header', ['fixedHeader' => false])
@endsection

@section('sidebar')
    <?php growtype_wc_include_view('partials.sidebar-shop') ?>
@endsection

@section('content')
    <div class="page page-checkout-data">
        <div class="maincontent">
            <div class="woocommerce container">
                <?php
                if (growtype_wc_user_can_manage_shop()) {
                    $order = get_user_first_order();
                    echo growtype_wc_include_view('woocommerce.checkout.thankyou', ['order' => $order]);
                }
                ?>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    @include('partials.sections.footer')
@endsection
