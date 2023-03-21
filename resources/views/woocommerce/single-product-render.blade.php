@extends('layouts.app')

@section('header')
    @include('partials.sections.header', ['fixedHeader' => false])
@endsection

@push('pageStyles')
    <style>
        @if(class_exists('ACF') && get_field('top_section_color'))
        .site-header, .woocommerce-breadcrumb, .product-summary {
            background: <?php echo get_field('top_section_color')?>                               !important;
        }

        header a, .product-summary, .woocommerce.single-product .woocommerce-breadcrumb a {
            color: <?php echo get_field('top_section_text_color')?>                 !important;
        }
        @endif
    </style>
@endpush

@section('content')
    <?php
    /**
     * The Template for displaying all single products
     *
     * This template can be overridden by copying it to yourtheme/woocommerce/single-product.php.
     *
     * HOWEVER, on occasion WooCommerce will need to update template files and you
     * (the theme developer) will need to copy the new files to your theme to
     * maintain compatibility. We try to do this as little as possible, but it does
     * happen. When this occurs the version of the template file will be bumped and
     * the readme will list any important changes.
     *
     * @see        https://docs.woocommerce.com/document/template-structure/
     * @package    WooCommerce/Templates
     * @version     1.6.4
     */

    if (!defined('ABSPATH')) {
        exit; // Exit if accessed directly
    }
    ?>

    <?php
    /**
     * woocommerce_before_main_content hook.
     *
     * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
     * @hooked woocommerce_breadcrumb - 20
     */
    do_action('woocommerce_before_main_content');
    ?>

    <?php while (have_posts()) : the_post(); ?>

        <?php do_action('growtype_wc_single_product_main_content') ?>

    <?php endwhile; // end of the loop. ?>

    <?php
    /**
     * woocommerce_after_main_content hook.
     *
     * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
     */
    do_action('woocommerce_after_main_content');
    ?>
@endsection

@section('panel')
    @include('partials.content.content-panel')
@endsection

@section('sidebar')
    @include('partials.content.content-sidebar-primary')
@endsection

@section('footer')
    @include('partials.sections.footer')
@endsection