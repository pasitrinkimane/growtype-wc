<form class="checkout_coupon woocommerce-form-coupon" method="post" style="display:none">

    <p><?php esc_html_e('If you have a coupon code, please apply it below.', 'growtype-wc'); ?></p>

    <div class="woocommerce-form-coupon-content">
        <div class="input-wrapper">
            <input type="text" name="coupon_code" class="input-text h-100" placeholder="<?php esc_attr_e('Coupon code', 'growtype-wc'); ?>" id="coupon_code" value=""/>
        </div>
        <div class="submit-wrapper">
            <button type="submit" class="button btn btn-primary w-100" name="apply_coupon" value="<?php esc_attr_e('Apply coupon', 'growtype-wc'); ?>"><?php esc_html_e('Apply', 'growtype-wc'); ?></button>
        </div>
    </div>

    <div class="clear"></div>
</form>
