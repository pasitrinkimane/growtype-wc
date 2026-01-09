<div class="woocommerce-order-details-intro">
    <?php
    $intro_text = growtype_wc_thankyou_page_intro_content();
    $intro_text = $intro_text ?: 'Unfortunately, we could not find this order. Your purchase may not have been completed. Please try again. <div><a href="#" class="btn btn-primary mt-4">Continue to checkout</a></div>';

    echo apply_filters('the_content', $intro_text); ?>
</div>
