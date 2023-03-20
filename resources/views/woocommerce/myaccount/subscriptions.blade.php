<?php
defined('ABSPATH') || exit;

do_action('woocommerce_before__account_subscriptions');
?>

@if(!empty($products))
    <div class="board-box">
        @foreach($products as $product)
            <h3>{!! $product->get_title() !!}</h3>
            <div class="row">
                <div class="col-lg-6">
                    <strong>{!! __('Details','growtype-wc') !!}</strong>
                    <div>{!! __('Price','growtype-wc') !!}: {!! $product->get_price_html() !!}</div>
                    <div>{!! __('Status','growtype-wc') !!}: {!! __('Active','growtype-wc') !!}</div>
                    <div>{!! __('Next charge','growtype-wc') !!}: {!! __('March 26, 2022 02:00','growtype-wc') !!}</div>
                </div>
                <div class="col-lg-6">
                    <div class="pull-right">
                        <a class="btn btn-primary" href="#">
                            {!! __('Change plan','growtype-wc') !!}
                        </a>
                        <a class="btn btn-secondary" href="#">
                            {!! __('Cancel plan','growtype-wc') !!}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    @include('partials.content.404.general', ['subtitle' => __('You have no subscriptions.', 'growtype-wc')])
@endif
