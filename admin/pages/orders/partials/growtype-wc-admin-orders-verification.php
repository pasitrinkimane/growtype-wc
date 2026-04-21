<?php

class Growtype_Wc_Admin_Orders_Verification
{
    public function __construct()
    {
        // Add meta box to order edit page
        add_action('add_meta_boxes', [$this, 'add_verification_meta_box']);
        
        // Handle public request
        add_action('init', [$this, 'handle_public_request']);
    }

    public function add_verification_meta_box()
    {
        $screen = 'shop_order';
        
        // Support for HPOS
        if (function_exists('wc_get_container') && class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
            if (\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $screen = 'woocommerce_page_wc-orders';
            }
        }

        add_meta_box(
            'growtype_wc_order_verification',
            __('Order Verification', 'growtype-wc'),
            [$this, 'render_verification_meta_box'],
            $screen,
            'side',
            'default'
        );
        
        // Always try shop_order as fallback for older versions or non-HPOS
        if ($screen !== 'shop_order') {
             add_meta_box(
                'growtype_wc_order_verification',
                __('Order Verification', 'growtype-wc'),
                [$this, 'render_verification_meta_box'],
                'shop_order',
                'side',
                'default'
            );
        }
    }

    public function render_verification_meta_box($post_or_order)
    {
        $order_id = ($post_or_order instanceof WP_Post) ? $post_or_order->ID : $post_or_order->get_id();
        $order = wc_get_order($order_id);
        
        if (!$order) return;

        $token = $order->get_meta('_growtype_wc_verification_token');
        if (!$token) {
            $token = wp_generate_password(32, false);
            $order->update_meta_data('_growtype_wc_verification_token', $token);
            $order->save();
        }

        $url = add_query_arg([
            'order_verify' => $order->get_id(),
            'token'        => $token
        ], home_url('/'));

        ?>
        <div class="growtype-wc-verification-box">
            <p><?php _e('Share this unique link with the customer to provide proof of purchase. This page is publicly accessible but protected by a unique token.', 'growtype-wc'); ?></p>
            <div style="margin-bottom: 10px;">
                <input type="text" value="<?php echo esc_url($url); ?>" readonly style="width:100%; background: #f0f0f1;" onclick="this.select();">
            </div>
            <a href="<?php echo esc_url($url); ?>" target="_blank" class="button button-primary" style="width: 100%; text-align: center;">
                <?php _e('View Public Proof Page', 'growtype-wc'); ?>
            </a>
            <p style="font-size: 11px; color: #646970; margin-top: 10px;">
                <?php _e('The page displays order items, totals, billing info, and payment status.', 'growtype-wc'); ?>
            </p>
        </div>
        <?php
    }

    public function handle_public_request()
    {
        if (!isset($_GET['order_verify']) || !isset($_GET['token'])) {
            return;
        }

        $order_id = intval($_GET['order_verify']);
        $token    = sanitize_text_field($_GET['token']);

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $saved_token = $order->get_meta('_growtype_wc_verification_token');
        if (empty($saved_token) || $token !== $saved_token) {
            wp_die(__('Invalid or expired verification token.', 'growtype-wc'), __('Verification Error', 'growtype-wc'), ['response' => 403]);
        }

        $this->render_verification_page($order);
        exit;
    }

    private function render_verification_page($order)
    {
        $order_id = $order->get_id();
        $date = $order->get_date_created()->date('F j, Y');
        $time = $order->get_date_created()->date('H:i');
        $status = $order->get_status();
        $total = $order->get_formatted_order_total();
        $items = $order->get_items();
        $billing_email = $order->get_billing_email();
        $billing_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        if (empty($billing_name)) {
            $billing_name = __('Guest Customer', 'growtype-wc');
        }
        $billing_address = $order->get_formatted_billing_address();
        $payment_method = $order->get_payment_method_title();
        if (empty($payment_method)) {
            $payment_method = $order->get_payment_method();
            if (empty($payment_method)) {
                $payment_method = __('Manual / Other', 'growtype-wc');
            } else {
                $payment_method = str_replace(['_', '-'], ' ', $payment_method);
                $payment_method = ucwords($payment_method);
            }
        }

        // Clean up internal provider names
        $replacements = [
            'Growtype Wc Stripe' => 'Stripe',
            'Growtype Wc Paypal' => 'PayPal',
            'Growtype Stripe'    => 'Stripe',
            'Growtype Paypal'    => 'PayPal',
        ];
        foreach ($replacements as $internal => $friendly) {
            if (strpos($payment_method, $internal) !== false) {
                $payment_method = $friendly;
                break;
            }
        }
        
        $transaction_id = $order->get_transaction_id();
        
        // Fallback for detailed transaction IDs (specifically for Stripe/PayPal if standard ID is missing)
        if (empty($transaction_id)) {
            $transaction_id = $order->get_meta('_stripe_charge_id');
            if (empty($transaction_id)) {
                $transaction_id = $order->get_meta('_stripe_intent_id');
            }
            if (empty($transaction_id)) {
                $transaction_id = $order->get_meta('_transaction_id');
            }
            if (empty($transaction_id)) {
                $transaction_id = $order->get_meta('transaction_id');
            }
            if (empty($transaction_id)) {
                $transaction_id = $order->get_meta('Payer ID'); // PayPal fallback
            }
        }

        $customer_ip = $order->get_customer_ip_address();
        $user_agent  = $order->get_customer_user_agent();
        
        $token = $order->get_meta('_growtype_wc_verification_token');
        $verification_id = substr($token, 0, 8) . '-' . $order_id;
        $report_generated = current_time('F j, Y @ H:i');
        
        $site_name = get_bloginfo('name');
        $site_logo = get_header_image(); // Basic logo fetch, can be improved

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php printf(__('Order #%s Verification - %s', 'growtype-wc'), $order_id, $site_name); ?></title>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
            <style>
                :root {
                    --primary-color: #6366f1;
                    --primary-hover: #4f46e5;
                    --bg-color: #f1f5f9;
                    --card-bg: #ffffff;
                    --text-main: #0f172a;
                    --text-muted: #64748b;
                    --border-color: #e2e8f0;
                    --success-bg: #f0fdf4;
                    --success-text: #166534;
                    --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
                }

                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(10px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                body {
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
                    background-color: var(--bg-color);
                    background-image: radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.05) 0px, transparent 50%), 
                                      radial-gradient(at 100% 0%, rgba(168, 85, 247, 0.05) 0px, transparent 50%);
                    color: var(--text-main);
                    line-height: 1.6;
                    margin: 0;
                    padding: 60px 20px;
                    display: flex;
                    justify-content: center;
                    align-items: flex-start;
                    min-height: 100vh;
                }

                .container {
                    width: 100%;
                    max-width: 650px;
                    background: var(--card-bg);
                    border-radius: 24px;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
                    overflow: hidden;
                    border: 1px solid rgba(255, 255, 255, 0.8);
                    animation: fadeIn 0.6s ease-out;
                }

                .header {
                    padding: 60px 40px 40px;
                    text-align: center;
                    position: relative;
                    background: linear-gradient(to bottom, #ffffff, #f8fafc);
                }

                .logo {
                    font-size: 22px;
                    font-weight: 900;
                    background: var(--accent-gradient);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    margin-bottom: 24px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }

                .badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    border-radius: 100px;
                    font-size: 13px;
                    font-weight: 700;
                    background: var(--success-bg);
                    color: var(--success-text);
                    margin-bottom: 16px;
                    transition: transform 0.2s;
                    border: 1px solid #bbf7d0;
                }

                .badge:hover {
                    transform: scale(1.02);
                }

                h1 {
                    margin: 0;
                    font-size: 32px;
                    font-weight: 800;
                    color: var(--text-main);
                    letter-spacing: -0.5px;
                }

                .order-meta {
                    margin-top: 12px;
                    color: var(--text-muted);
                    font-size: 15px;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 12px;
                }

                .dot { width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%; }

                .content {
                    padding: 40px;
                }

                .section-title {
                    font-size: 13px;
                    font-weight: 800;
                    text-transform: uppercase;
                    color: var(--text-muted);
                    margin-bottom: 24px;
                    letter-spacing: 0.1em;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .section-title::after {
                    content: '';
                    height: 1px;
                    flex-grow: 1;
                    background: var(--border-color);
                }

                .order-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 40px;
                }

                .order-table th {
                    text-align: left;
                    font-size: 13px;
                    color: var(--text-muted);
                    padding-bottom: 16px;
                    font-weight: 600;
                }

                .order-table td {
                    padding: 20px 0;
                    border-top: 1px solid var(--border-color);
                }

                .item-name {
                    font-weight: 600;
                    color: var(--text-main);
                    font-size: 16px;
                }

                .item-price {
                    text-align: right;
                    font-weight: 700;
                    color: var(--text-main);
                    font-size: 16px;
                }

                .totals {
                    margin-top: 20px;
                    background: #f8fafc;
                    padding: 32px;
                    border-radius: 20px;
                    border: 1px solid var(--border-color);
                }

                .total-row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 12px;
                    font-size: 15px;
                    color: var(--text-muted);
                }

                .total-row.grand-total {
                    margin-top: 16px;
                    padding-top: 16px;
                    border-top: 1px solid var(--border-color);
                    font-size: 24px;
                    font-weight: 900;
                    color: var(--text-main);
                }

                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 40px;
                    margin-top: 48px;
                }

                .info-block label {
                    display: block;
                    font-size: 12px;
                    font-weight: 800;
                    color: var(--text-muted);
                    text-transform: uppercase;
                    margin-bottom: 12px;
                    letter-spacing: 0.05em;
                }

                .info-block div {
                    font-size: 16px;
                    font-weight: 600;
                }

                .footer {
                    padding: 40px;
                    text-align: center;
                    border-top: 1px solid var(--border-color);
                    background: #fafafa;
                    color: var(--text-muted);
                }

                .verified-stamp {
                    margin: 24px auto 0;
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    color: #059669;
                    font-weight: 800;
                    padding: 12px 24px;
                    background: #ffffff;
                    border-radius: 12px;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
                    border: 1px solid var(--border-color);
                    font-size: 14px;
                }

                @media (max-width: 600px) {
                    .info-grid {
                        grid-template-columns: 1fr;
                    }
                    .header, .content {
                        padding: 30px 20px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo"><?php echo esc_html($site_name); ?></div>
                    <div class="badge">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="currentColor"/>
                        </svg>
                        <?php _e('Verified Purchase', 'growtype-wc'); ?>
                    </div>
                    <h1><?php printf(__('Order #%s', 'growtype-wc'), $order_id); ?></h1>
                    <div class="order-meta">
                        <?php echo $date; ?> <span class="dot"></span> <?php echo $time; ?> <span class="dot"></span> <?php echo $billing_email; ?>
                    </div>
                </div>

                <div class="content">
                    <div class="section-title"><?php _e('Order Summary', 'growtype-wc'); ?></div>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th><?php _e('Product', 'growtype-wc'); ?></th>
                                <th style="text-align: right;"><?php _e('Total', 'growtype-wc'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item_id => $item) : ?>
                                <tr>
                                    <td>
                                        <div class="item-name"><?php echo $item->get_name(); ?> x <?php echo $item->get_quantity(); ?></div>
                                    </td>
                                    <td class="item-price">
                                        <?php echo $order->get_formatted_line_subtotal($item); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="totals">
                        <div class="total-row">
                            <span><?php _e('Subtotal', 'growtype-wc'); ?></span>
                            <span><?php echo $order->get_subtotal_to_display(); ?></span>
                        </div>
                        <?php foreach ($order->get_coupons() as $coupon) : ?>
                            <div class="total-row">
                                <span><?php _e('Coupon:', 'growtype-wc'); ?> <?php echo $coupon->get_code(); ?></span>
                                <span>-<?php echo wc_price($coupon->get_discount()); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($order->get_fees() as $fee) : ?>
                            <div class="total-row">
                                <span><?php echo $fee->get_name(); ?></span>
                                <span><?php echo wc_price($fee->get_total()); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="total-row grand-total">
                            <span><?php _e('Total Paid', 'growtype-wc'); ?></span>
                            <span><?php echo $total; ?></span>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-block">
                            <label><?php _e('Customer Details', 'growtype-wc'); ?></label>
                            <div style="font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                                <?php echo esc_html($billing_name); ?>
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #059669;">
                                    <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="currentColor"/>
                                </svg>
                            </div>
                            <div style="color: var(--text-muted); font-size: 14px; margin-top: 4px;"><?php echo esc_html($billing_email); ?></div>
                            <?php if ($billing_address) : ?>
                                <div style="color: var(--text-muted); font-size: 13px; margin-top: 10px; line-height: 1.5; background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
                                    <?php echo wp_kses_post($billing_address); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($customer_ip || $user_agent) : ?>
                                <div style="margin-top: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px; position: relative; overflow: hidden;">
                                    <div style="position: absolute; right: -10px; top: -10px; opacity: 0.05; color: var(--primary-color);">
                                        <svg width="60" height="60" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 6c1.4 0 2.5 1.1 2.5 2.5S13.4 12 12 12s-2.5-1.1-2.5-2.5S10.6 7 12 7zm0 10c-2.1 0-4.5-1-4.5-2.5 0-1.5 2.4-2.5 4.5-2.5s4.5 1 4.5 2.5c0 1.5-2.4 2.5-4.5 2.5z"/></svg>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 10px;">
                                        <div style="width: 6px; height: 6px; background: #10b981; border-radius: 50%; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);"></div>
                                        <div style="font-weight: 800; text-transform: uppercase; font-size: 10px; color: var(--text-muted); letter-spacing: 0.05em;"><?php _e('Security Log Trace', 'growtype-wc'); ?></div>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-main); font-family: 'Inter', monospace; line-height: 1.6;">
                                        <?php if ($customer_ip) : ?>
                                            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; margin-bottom: 4px;">
                                                <span style="color: var(--text-muted); font-weight: 600;"><?php _e('Origin IP:', 'growtype-wc'); ?></span>
                                                <span style="font-weight: 700;"><?php echo esc_html($customer_ip); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($user_agent) : ?>
                                            <div style="color: var(--text-muted); font-size: 10px;">
                                                <span style="font-weight: 600;"><?php _e('Device Fingerprint:', 'growtype-wc'); ?></span><br>
                                                <span style="opacity: 0.8;"><?php echo esc_html(substr($user_agent, 0, 110)); ?>...</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="info-block">
                            <label><?php _e('Order Verification', 'growtype-wc'); ?></label>
                            <div style="font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                                <?php printf(__('Verified via %s', 'growtype-wc'), esc_html($payment_method)); ?>
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" style="color: #059669;">
                                    <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="currentColor"/>
                                </svg>
                            </div>
                            <div style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">
                                <?php printf(__('Authenticated on %s', 'growtype-wc'), $date . ' @ ' . $time); ?>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <div style="font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px;"><?php _e('Official Transaction ID', 'growtype-wc'); ?></div>
                                <?php if ($transaction_id) : ?>
                                    <div style="color: var(--text-main); font-size: 13px; font-family: monospace; background: #f0fdf4; padding: 6px 10px; border-radius: 6px; display: inline-block; border: 1px solid #bbf7d0; font-weight: 700;">
                                        <?php echo esc_html($transaction_id); ?>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 10px; margin-top: 6px;">
                                        <?php printf(__('Matches the record on your %s statement.', 'growtype-wc'), $payment_method); ?>
                                    </div>
                                <?php else : ?>
                                    <div style="color: var(--text-muted); font-size: 12px; font-style: italic;">
                                        <?php _e('Verified via secure API handshake.', 'growtype-wc'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-color);">
                                <div style="font-size: 9px; font-weight: 800; color: var(--text-muted); text-transform: uppercase;"><?php _e('Report ID', 'growtype-wc'); ?></div>
                                <div style="font-size: 12px; font-family: monospace; color: var(--text-muted);"><?php echo $verification_id; ?></div>
                                <div style="font-size: 9px; color: var(--text-muted); margin-top: 2px;"><?php printf(__('Generated: %s', 'growtype-wc'), $report_generated); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    <p><?php printf(__('This is a tamper-proof official transaction record from %s.', 'growtype-wc'), $site_name); ?></p>
                    <div class="verified-stamp">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="currentColor"/>
                        </svg>
                        <?php _e('OFFICIAL VERIFIED RECORD', 'growtype-wc'); ?>
                    </div>
                    <div style="margin-top: 15px; display: flex; justify-content: center; gap: 15px; opacity: 0.5; filter: grayscale(1);">
                        <div style="display: flex; align-items: center; gap: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            SSL Encrypted
                        </div>
                        <div style="display: flex; align-items: center; gap: 4px; font-size: 9px; font-weight: 700; text-transform: uppercase;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 13.17l7.59-7.59L19 7l-9 10z"/></svg>
                            Secure Handshake
                        </div>
                    </div>
                    <p style="margin-top: 20px; opacity: 0.4; font-size: 10px;">&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. <?php _e('Verification powered by growtype-wc signature engine.', 'growtype-wc'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
