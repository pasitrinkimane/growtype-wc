@extends('layouts.app')

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
                @if(growtype_wc_user_can_manage_shop())
                    <?php
                    $order = get_user_first_order();
                    ?>
                    @include('woocommerce.checkout.thankyou')
                @endif
            </div>
        </div>
    </div>
@endsection

@section('footer')
    @include('partials.sections.footer')
@endsection
