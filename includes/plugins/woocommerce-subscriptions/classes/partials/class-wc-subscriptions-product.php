<?php

class WC_Subscriptions_Product
{
    public static $version = '5.5.0'; // WRCS: DEFINED_VERSION.

    public static function is_subscription( $product ) {

        $is_subscription = $product_id = false;

        $product = self::maybe_get_product_instance( $product );

        if ( is_object( $product ) ) {

            $product_id = $product->get_id();

            if ( $product->is_type( array( 'subscription', 'subscription_variation', 'variable-subscription' ) ) ) {
                $is_subscription = true;
            }
        }

        return apply_filters( 'woocommerce_is_subscription', $is_subscription, $product_id, $product );
    }

    private static function maybe_get_product_instance( $product ) {

        if ( ! is_object( $product ) || ! is_a( $product, 'WC_Product' ) ) {
            $product = wc_get_product( $product );
        }

        return $product;
    }
}
