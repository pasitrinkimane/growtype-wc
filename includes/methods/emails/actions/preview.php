<?php

/**
 * @return null
 */
add_action('wp_ajax_preview_email', 'growtype_wc_preview_email');
function growtype_wc_preview_email()
{
    global $woocommerce;

    if (!growtype_wc_user_can_manage_shop()) {
        return null;
    }

    $mailer = $woocommerce->mailer();
    $email_options = array ();

    foreach ($mailer->emails as $key => $obj) {
        $email_options[$key] = $obj->title;
    }

    $order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';
    $in_email_type = isset($_GET['email_type']) ? $_GET['email_type'] : '';

    if (empty($order_id)) {
        $order_id = growtype_wc_get_user_first_order()->get_id();
    }

    $order_number = is_numeric($order_id) ? (int)$order_id : '';
    $email_class = isset($email_options[$in_email_type]) ? $in_email_type : '';
    $order = $order_number ? wc_get_order($order_number) : false;

    $error = '';
    $email_html = '';

    if (!$order_id && !$in_email_type) {
        $error = '<p>Please select an email type and enter an order #</p>';
    } elseif (!$email_class) {
        $error = '<p>Bad email type</p>';
    } elseif (!$order) {
        $error = '<p>No order information</p>';
    } else {
        $email = $mailer->emails[$email_class];
        $email->object = $order;
        $email_html = apply_filters('woocommerce_mail_content', $email->style_inline($email->get_content_html()));
    }
    ?>
    <!DOCTYPE HTML>
    <html>
    <head></head>
    <body>
    <form method="get" action="<?php echo site_url(); ?>/wp-admin/admin-ajax.php" style="position: absolute;text-align: center;left: 0;right: 0;top: 20px;">
        <input type="hidden" name="action" value="preview_email">
        <select name="email_type">
            <?php
            foreach ($email_options as $class => $label) {
                if ($email_class && $class == $email_class) {
                    $selected = 'selected';
                } else {
                    $selected = '';
                }
                ?>
                <option value="<?php echo $class; ?>" <?php echo $selected; ?> ><?php echo $label; ?></option>
            <?php } ?>
        </select>
        <input type="text" name="order_id" value="<?php echo $order_number; ?>" placeholder="order #">
        <input type="submit" value="Go">
    </form>
    <?php
    if ($error) {
        echo "<div class='error'>$error</div>";
    } else {
        echo $email_html;
    }
    ?>
    </body>
    </html>

    <?php
    return null;
}

/**
 * @param $order
 * @return mixed|null
 */
function growtype_wc_email_customer_completed_order_main_content($order)
{
    $content = get_theme_mod('wc_email_customer_completed_order_main_content');

    if (!empty($content)) {
        $content = str_replace("{customer_name}", esc_html($order->get_billing_first_name()), $content);
        $content = str_replace("{date_created}", esc_html(wc_format_datetime($order->get_date_created())), $content);
        $content = nl2br($content);
    }

    return apply_filters('growtype_wc_email_customer_completed_order_main_content', $content, $order);
}
